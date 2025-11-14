<?php

namespace Tests\Feature\Services;

use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationContent;
use App\Models\CreationDraft;
use App\Models\CreationDraftContent;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\ContentManagementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ContentManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentManagementService();
    }

    /**
     * Data provider for all parent entity types
     */
    public static function parentEntityProvider(): array
    {
        return [
            'BlogPostDraft' => [
                'factory' => fn () => BlogPostDraft::factory()->create(),
                'contentBlockClass' => BlogPostDraftContent::class,
            ],
            'BlogPost' => [
                'factory' => fn () => BlogPost::factory()->create(),
                'contentBlockClass' => BlogPostContent::class,
            ],
            'CreationDraft' => [
                'factory' => fn () => CreationDraft::factory()->create(),
                'contentBlockClass' => CreationDraftContent::class,
            ],
            'Creation' => [
                'factory' => fn () => Creation::factory()->create(),
                'contentBlockClass' => CreationContent::class,
            ],
        ];
    }

    #[Test]
    #[Group('create')]
    #[DataProvider('parentEntityProvider')]
    public function creates_markdown_content_for_all_parent_types(callable $factory, string $contentBlockClass): void
    {
        $parent = $factory();
        $translationKey = TranslationKey::factory()->create();
        $order = 1;

        $contentBlock = $this->service->createMarkdownContent($parent, $translationKey->id, $order);

        $this->assertInstanceOf($contentBlockClass, $contentBlock);
        $this->assertEquals($order, $contentBlock->order);
        $this->assertEquals(ContentMarkdown::class, $contentBlock->content_type);

        $this->assertDatabaseHas('content_markdowns', [
            'id' => $contentBlock->content_id,
            'translation_key_id' => $translationKey->id,
        ]);
    }

    #[Test]
    #[Group('create')]
    public function creates_markdown_content_with_correct_order(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $contentBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 5);

        $this->assertEquals(5, $contentBlock->order);
    }

    #[Test]
    #[Group('create')]
    #[DataProvider('parentEntityProvider')]
    public function creates_gallery_content_with_pictures(callable $factory, string $contentBlockClass): void
    {
        $parent = $factory();
        $pictures = Picture::factory()->count(3)->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => [
                $pictures[0]->id,
                $pictures[1]->id,
                $pictures[2]->id,
            ],
        ];

        $contentBlock = $this->service->createGalleryContent($parent, $galleryData, 1);

        $this->assertInstanceOf($contentBlockClass, $contentBlock);
        $this->assertEquals(ContentGallery::class, $contentBlock->content_type);

        $this->assertDatabaseHas('content_galleries', [
            'id' => $contentBlock->content_id,
            'layout' => 'grid',
            'columns' => 3,
        ]);

        // Verify picture attachments with correct order
        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $contentBlock->content_id,
            'picture_id' => $pictures[0]->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $contentBlock->content_id,
            'picture_id' => $pictures[1]->id,
            'order' => 2,
        ]);

        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $contentBlock->content_id,
            'picture_id' => $pictures[2]->id,
            'order' => 3,
        ]);
    }

    #[Test]
    #[Group('create')]
    public function creates_gallery_content_with_empty_pictures_array(): void
    {
        $parent = BlogPostDraft::factory()->create();

        $galleryData = [
            'layout' => 'carousel',
            'columns' => null,
            'pictures' => [],
        ];

        $contentBlock = $this->service->createGalleryContent($parent, $galleryData, 1);

        $this->assertDatabaseHas('content_galleries', [
            'id' => $contentBlock->content_id,
            'layout' => 'carousel',
            'columns' => null,
        ]);

        $this->assertDatabaseCount('content_gallery_pictures', 0);
    }

    #[Test]
    #[Group('create')]
    public function creates_gallery_content_without_pictures_key(): void
    {
        $parent = CreationDraft::factory()->create();

        $galleryData = [
            'layout' => 'masonry',
            'columns' => 2,
        ];

        $contentBlock = $this->service->createGalleryContent($parent, $galleryData, 2);

        $this->assertDatabaseHas('content_galleries', [
            'id' => $contentBlock->content_id,
            'layout' => 'masonry',
            'columns' => 2,
        ]);

        $this->assertDatabaseCount('content_gallery_pictures', 0);
    }

    #[Test]
    #[Group('create')]
    public function creates_gallery_with_null_columns(): void
    {
        $parent = Creation::factory()->create();

        $galleryData = [
            'layout' => 'list',
            'columns' => null,
        ];

        $contentBlock = $this->service->createGalleryContent($parent, $galleryData, 1);

        $this->assertDatabaseHas('content_galleries', [
            'id' => $contentBlock->content_id,
            'layout' => 'list',
            'columns' => null,
        ]);
    }

    #[Test]
    #[Group('create')]
    public function creates_gallery_with_correct_picture_order_indexing(): void
    {
        $parent = BlogPost::factory()->create();
        $pictures = Picture::factory()->count(5)->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => $pictures->pluck('id')->toArray(),
        ];

        $contentBlock = $this->service->createGalleryContent($parent, $galleryData, 1);

        // Verify 1-based indexing for picture order
        foreach ($pictures as $index => $picture) {
            $this->assertDatabaseHas('content_gallery_pictures', [
                'gallery_id' => $contentBlock->content_id,
                'picture_id' => $picture->id,
                'order' => $index + 1, // 1-based indexing
            ]);
        }
    }

    #[Test]
    #[Group('create')]
    #[DataProvider('parentEntityProvider')]
    public function creates_video_content_with_caption(callable $factory, string $contentBlockClass): void
    {
        $parent = $factory();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $contentBlock = $this->service->createVideoContent($parent, $video->id, 1, $captionKey->id);

        $this->assertInstanceOf($contentBlockClass, $contentBlock);
        $this->assertEquals(ContentVideo::class, $contentBlock->content_type);

        $this->assertDatabaseHas('content_videos', [
            'id' => $contentBlock->content_id,
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);
    }

    #[Test]
    #[Group('create')]
    public function creates_video_content_without_caption(): void
    {
        $parent = CreationDraft::factory()->create();
        $video = Video::factory()->create();

        $contentBlock = $this->service->createVideoContent($parent, $video->id, 2, null);

        $this->assertDatabaseHas('content_videos', [
            'id' => $contentBlock->content_id,
            'video_id' => $video->id,
            'caption_translation_key_id' => null,
        ]);
    }

    #[Test]
    #[Group('create')]
    public function creates_video_content_with_correct_order(): void
    {
        $parent = BlogPost::factory()->create();
        $video = Video::factory()->create();

        $contentBlock = $this->service->createVideoContent($parent, $video->id, 7);

        $this->assertEquals(7, $contentBlock->order);
    }

    #[Test]
    #[Group('update')]
    public function updates_markdown_content_translation_key(): void
    {
        $originalKey = TranslationKey::factory()->create();
        $newKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $originalKey->id,
        ]);

        $updated = $this->service->updateMarkdownContent($markdown, $newKey->id);

        $this->assertInstanceOf(ContentMarkdown::class, $updated);
        $this->assertEquals($newKey->id, $updated->translation_key_id);

        $this->assertDatabaseHas('content_markdowns', [
            'id' => $markdown->id,
            'translation_key_id' => $newKey->id,
        ]);
    }

    #[Test]
    #[Group('update')]
    public function updates_markdown_returns_refreshed_instance(): void
    {
        $originalKey = TranslationKey::factory()->create();
        $newKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $originalKey->id,
        ]);

        $updated = $this->service->updateMarkdownContent($markdown, $newKey->id);

        $this->assertEquals($markdown->id, $updated->id);
        $this->assertEquals($newKey->id, $updated->translation_key_id);
    }

    #[Test]
    #[Group('update')]
    public function updates_gallery_layout_and_columns(): void
    {
        $gallery = ContentGallery::factory()->create([
            'layout' => 'grid',
            'columns' => 2,
        ]);

        $updated = $this->service->updateGalleryContent($gallery, [
            'layout' => 'masonry',
            'columns' => 4,
        ]);

        $this->assertEquals('masonry', $updated->layout);
        $this->assertEquals(4, $updated->columns);

        $this->assertDatabaseHas('content_galleries', [
            'id' => $gallery->id,
            'layout' => 'masonry',
            'columns' => 4,
        ]);
    }

    #[Test]
    #[Group('update')]
    public function updates_gallery_pictures_replaces_existing(): void
    {
        $gallery = ContentGallery::factory()->create();
        $oldPictures = Picture::factory()->count(2)->create();
        $newPictures = Picture::factory()->count(3)->create();

        // Attach old pictures
        foreach ($oldPictures as $index => $pic) {
            $gallery->pictures()->attach($pic->id, [
                'order' => $index + 1,
            ]);
        }

        $updated = $this->service->updateGalleryContent($gallery, [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => $newPictures->pluck('id')->toArray(),
        ]);

        // Old pictures should be detached
        foreach ($oldPictures as $pic) {
            $this->assertDatabaseMissing('content_gallery_pictures', [
                'gallery_id' => $gallery->id,
                'picture_id' => $pic->id,
            ]);
        }

        // New pictures should be attached
        foreach ($newPictures as $index => $pic) {
            $this->assertDatabaseHas('content_gallery_pictures', [
                'gallery_id' => $gallery->id,
                'picture_id' => $pic->id,
                'order' => $index + 1,
            ]);
        }

        $this->assertCount(3, $updated->pictures);
    }

    #[Test]
    #[Group('update')]
    public function updates_gallery_with_empty_pictures_array_removes_all(): void
    {
        $gallery = ContentGallery::factory()->create();
        $pictures = Picture::factory()->count(3)->create();

        foreach ($pictures as $index => $pic) {
            $gallery->pictures()->attach($pic->id, [
                'order' => $index + 1,
                'caption_translation_key_id' => null,
            ]);
        }

        $updated = $this->service->updateGalleryContent($gallery, [
            'layout' => 'list',
            'columns' => null,
            'pictures' => [],
        ]);

        $this->assertDatabaseCount('content_gallery_pictures', 0);
        $this->assertCount(0, $updated->pictures);
    }

    #[Test]
    #[Group('update')]
    public function updates_gallery_without_pictures_key_leaves_unchanged(): void
    {
        $gallery = ContentGallery::factory()->create();
        $pictures = Picture::factory()->count(2)->create();

        foreach ($pictures as $index => $pic) {
            $gallery->pictures()->attach($pic->id, [
                'order' => $index + 1,
                'caption_translation_key_id' => null,
            ]);
        }

        $updated = $this->service->updateGalleryContent($gallery, [
            'layout' => 'carousel',
            'columns' => 1,
        ]);

        // Pictures should still be attached
        foreach ($pictures as $pic) {
            $this->assertDatabaseHas('content_gallery_pictures', [
                'gallery_id' => $gallery->id,
                'picture_id' => $pic->id,
            ]);
        }

        $this->assertCount(2, $updated->pictures);
    }

    #[Test]
    #[Group('update')]
    public function updates_gallery_loads_pictures_relationship(): void
    {
        $gallery = ContentGallery::factory()->create();
        $pictures = Picture::factory()->count(2)->create();

        $updated = $this->service->updateGalleryContent($gallery, [
            'layout' => 'grid',
            'columns' => 2,
            'pictures' => $pictures->pluck('id')->toArray(),
        ]);

        $this->assertTrue($updated->relationLoaded('pictures'));
        $this->assertCount(2, $updated->pictures);
    }

    #[Test]
    #[Group('update')]
    public function updates_gallery_with_null_columns(): void
    {
        $gallery = ContentGallery::factory()->create(['columns' => 3]);

        $updated = $this->service->updateGalleryContent($gallery, [
            'layout' => 'list',
            'columns' => null,
        ]);

        $this->assertNull($updated->columns);
        $this->assertDatabaseHas('content_galleries', [
            'id' => $gallery->id,
            'columns' => null,
        ]);
    }

    #[Test]
    #[Group('update')]
    public function updates_video_content_videos_and_caption(): void
    {
        $originalVideo = Video::factory()->create();
        $originalCaption = TranslationKey::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $originalVideo->id,
            'caption_translation_key_id' => $originalCaption->id,
        ]);

        $newVideo = Video::factory()->create();
        $newCaption = TranslationKey::factory()->create();

        $updated = $this->service->updateVideoContent($videoContent, $newVideo->id, $newCaption->id);

        $this->assertEquals($newVideo->id, $updated->video_id);
        $this->assertEquals($newCaption->id, $updated->caption_translation_key_id);

        $this->assertDatabaseHas('content_videos', [
            'id' => $videoContent->id,
            'video_id' => $newVideo->id,
            'caption_translation_key_id' => $newCaption->id,
        ]);
    }

    #[Test]
    #[Group('update')]
    public function updates_video_content_with_null_caption(): void
    {
        $originalVideo = Video::factory()->create();
        $originalCaption = TranslationKey::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $originalVideo->id,
            'caption_translation_key_id' => $originalCaption->id,
        ]);

        $newVideo = Video::factory()->create();

        $updated = $this->service->updateVideoContent($videoContent, $newVideo->id, null);

        $this->assertEquals($newVideo->id, $updated->video_id);
        $this->assertNull($updated->caption_translation_key_id);

        $this->assertDatabaseHas('content_videos', [
            'id' => $videoContent->id,
            'video_id' => $newVideo->id,
            'caption_translation_key_id' => null,
        ]);
    }

    #[Test]
    #[Group('update')]
    public function updates_video_returns_refreshed_instance(): void
    {
        $videoContent = ContentVideo::factory()->create();
        $newVideo = Video::factory()->create();

        $updated = $this->service->updateVideoContent($videoContent, $newVideo->id);

        $this->assertEquals($videoContent->id, $updated->id);
        $this->assertEquals($newVideo->id, $updated->video_id);
    }

    #[Test]
    #[Group('update')]
    public function updates_video_from_caption_to_no_caption(): void
    {
        $video = Video::factory()->create();
        $caption = TranslationKey::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $caption->id,
        ]);

        $updated = $this->service->updateVideoContent($videoContent, $video->id, null);

        $this->assertNull($updated->caption_translation_key_id);
    }

    #[Test]
    #[Group('reorder')]
    #[DataProvider('parentEntityProvider')]
    public function reorders_content_blocks(callable $factory, string $contentBlockClass): void
    {
        $parent = $factory();
        $translationKeys = TranslationKey::factory()->count(4)->create();

        // Create 4 content blocks
        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 2);
        $block3 = $this->service->createMarkdownContent($parent, $translationKeys[2]->id, 3);
        $block4 = $this->service->createMarkdownContent($parent, $translationKeys[3]->id, 4);

        // Reorder: [block3, block1, block4, block2]
        $newOrder = [$block3->id, $block1->id, $block4->id, $block2->id];

        $this->service->reorderContent($parent, $newOrder);

        // Verify new order in database
        $this->assertDatabaseHas($contentBlockClass::make()->getTable(), [
            'id' => $block3->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas($contentBlockClass::make()->getTable(), [
            'id' => $block1->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas($contentBlockClass::make()->getTable(), [
            'id' => $block4->id,
            'order' => 3,
        ]);
        $this->assertDatabaseHas($contentBlockClass::make()->getTable(), [
            'id' => $block2->id,
            'order' => 4,
        ]);
    }

    #[Test]
    #[Group('reorder')]
    public function reorder_uses_one_based_indexing(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKeys = TranslationKey::factory()->count(3)->create();

        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 2);
        $block3 = $this->service->createMarkdownContent($parent, $translationKeys[2]->id, 3);

        $newOrder = [$block2->id, $block3->id, $block1->id];

        $this->service->reorderContent($parent, $newOrder);

        // Order should be 1, 2, 3 (not 0, 1, 2)
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $block2->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $block3->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $block1->id,
            'order' => 3,
        ]);
    }

    #[Test]
    #[Group('reorder')]
    public function reorder_updates_only_specified_content_ids(): void
    {
        $parent = CreationDraft::factory()->create();
        $translationKeys = TranslationKey::factory()->count(5)->create();

        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 2);
        $block3 = $this->service->createMarkdownContent($parent, $translationKeys[2]->id, 3);
        $block4 = $this->service->createMarkdownContent($parent, $translationKeys[3]->id, 4);
        $block5 = $this->service->createMarkdownContent($parent, $translationKeys[4]->id, 5);

        // Only reorder first 3 blocks
        $newOrder = [$block3->id, $block1->id, $block2->id];

        $this->service->reorderContent($parent, $newOrder);

        // First 3 should be reordered
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $block3->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $block1->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $block2->id,
            'order' => 3,
        ]);

        // Last 2 should remain unchanged
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $block4->id,
            'order' => 4,
        ]);
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $block5->id,
            'order' => 5,
        ]);
    }

    #[Test]
    #[Group('reorder')]
    public function reorder_handles_single_item_array(): void
    {
        $parent = Creation::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $block = $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        $this->service->reorderContent($parent, [$block->id]);

        $this->assertDatabaseHas('creation_contents', [
            'id' => $block->id,
            'order' => 1,
        ]);
    }

    #[Test]
    #[Group('reorder')]
    public function reorder_uses_database_transaction(): void
    {
        $parent = BlogPost::factory()->create();
        $translationKeys = TranslationKey::factory()->count(2)->create();

        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 2);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->service->reorderContent($parent, [$block2->id, $block1->id]);
    }

    #[Test]
    #[Group('reorder')]
    public function reorder_handles_empty_array_gracefully(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $block = $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        $this->service->reorderContent($parent, []);

        // Original order should remain
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $block->id,
            'order' => 1,
        ]);
    }

    #[Test]
    #[Group('delete')]
    public function deletes_markdown_content_block_and_entity(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $contentBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);
        $markdownId = $contentBlock->content_id;

        $result = $this->service->deleteContent($contentBlock);

        $this->assertTrue($result);

        // Content block should be deleted
        $this->assertDatabaseMissing('blog_post_draft_contents', [
            'id' => $contentBlock->id,
        ]);

        // ContentMarkdown entity should be deleted
        $this->assertDatabaseMissing('content_markdowns', [
            'id' => $markdownId,
        ]);
    }

    #[Test]
    #[Group('delete')]
    public function deletes_gallery_content_and_detaches_pictures(): void
    {
        $parent = CreationDraft::factory()->create();
        $pictures = Picture::factory()->count(3)->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => $pictures->pluck('id')->toArray(),
        ];

        $contentBlock = $this->service->createGalleryContent($parent, $galleryData, 1);
        $galleryId = $contentBlock->content_id;

        $result = $this->service->deleteContent($contentBlock);

        $this->assertTrue($result);

        // Content block should be deleted
        $this->assertDatabaseMissing('creation_draft_contents', [
            'id' => $contentBlock->id,
        ]);

        // ContentGallery entity should be deleted
        $this->assertDatabaseMissing('content_galleries', [
            'id' => $galleryId,
        ]);

        // Picture relationships should be removed
        foreach ($pictures as $pic) {
            $this->assertDatabaseMissing('content_gallery_pictures', [
                'gallery_id' => $galleryId,
                'picture_id' => $pic->id,
            ]);
        }

        // Pictures themselves should still exist
        foreach ($pictures as $pic) {
            $this->assertDatabaseHas('pictures', [
                'id' => $pic->id,
            ]);
        }
    }

    #[Test]
    #[Group('delete')]
    public function deletes_video_content_block_and_entity(): void
    {
        $parent = Creation::factory()->create();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $contentBlock = $this->service->createVideoContent($parent, $video->id, 1, $captionKey->id);
        $videoContentId = $contentBlock->content_id;

        $result = $this->service->deleteContent($contentBlock);

        $this->assertTrue($result);

        // Content block should be deleted
        $this->assertDatabaseMissing('creation_contents', [
            'id' => $contentBlock->id,
        ]);

        // ContentVideo entity should be deleted
        $this->assertDatabaseMissing('content_videos', [
            'id' => $videoContentId,
        ]);

        // Video itself should still exist
        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
        ]);
    }

    #[Test]
    #[Group('delete')]
    public function delete_returns_true_on_success(): void
    {
        $parent = BlogPost::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $contentBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        $result = $this->service->deleteContent($contentBlock);

        $this->assertTrue($result);
    }

    #[Test]
    #[Group('delete')]
    public function delete_uses_database_transaction(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $contentBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->service->deleteContent($contentBlock);
    }

    #[Test]
    #[Group('duplicate')]
    #[DataProvider('parentEntityProvider')]
    public function duplicates_markdown_content(callable $factory, string $contentBlockClass): void
    {
        $parent = $factory();
        $translationKey = TranslationKey::factory()->create();

        $originalBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);
        $originalMarkdown = ContentMarkdown::find($originalBlock->content_id);

        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $this->assertInstanceOf($contentBlockClass, $duplicatedBlock);
        $this->assertNotEquals($originalBlock->id, $duplicatedBlock->id);
        $this->assertEquals(ContentMarkdown::class, $duplicatedBlock->content_type);

        // New ContentMarkdown should be created
        $this->assertNotEquals($originalMarkdown->id, $duplicatedBlock->content_id);

        $duplicatedMarkdown = ContentMarkdown::find($duplicatedBlock->content_id);
        $this->assertEquals($translationKey->id, $duplicatedMarkdown->translation_key_id);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_markdown_creates_new_content_block_with_different_id(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $originalBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);
        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $this->assertNotEquals($originalBlock->id, $duplicatedBlock->id);

        // Both should exist in database
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $originalBlock->id,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $duplicatedBlock->id,
        ]);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_sets_order_to_max_plus_one(): void
    {
        $parent = CreationDraft::factory()->create();
        $translationKeys = TranslationKey::factory()->count(3)->create();

        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 2);
        $block3 = $this->service->createMarkdownContent($parent, $translationKeys[2]->id, 3);

        $duplicatedBlock = $this->service->duplicateContent($block2);

        $this->assertEquals(4, $duplicatedBlock->order);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicates_gallery_with_pictures(): void
    {
        $parent = Creation::factory()->create();
        $pictures = Picture::factory()->count(3)->create();
        $captionKeys = TranslationKey::factory()->count(2)->create();

        $galleryData = [
            'layout' => 'masonry',
            'columns' => 4,
            'pictures' => $pictures->pluck('id')->toArray(),
        ];

        $originalBlock = $this->service->createGalleryContent($parent, $galleryData, 1);
        $originalGallery = ContentGallery::find($originalBlock->content_id);

        // Manually set caption translation keys for some pictures
        $originalGallery->pictures()->updateExistingPivot($pictures[0]->id, [
            'caption_translation_key_id' => $captionKeys[0]->id,
        ]);
        $originalGallery->pictures()->updateExistingPivot($pictures[2]->id, [
            'caption_translation_key_id' => $captionKeys[1]->id,
        ]);
        $originalGallery->refresh();

        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        // New ContentGallery should be created
        $this->assertNotEquals($originalGallery->id, $duplicatedBlock->content_id);

        $duplicatedGallery = ContentGallery::with('pictures')->find($duplicatedBlock->content_id);

        // Layout and columns should be copied
        $this->assertEquals('masonry', $duplicatedGallery->layout);
        $this->assertEquals(4, $duplicatedGallery->columns);

        // Pictures should be copied
        $this->assertCount(3, $duplicatedGallery->pictures);

        // Verify pivot data is preserved
        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $duplicatedGallery->id,
            'picture_id' => $pictures[0]->id,
            'order' => 1,
            'caption_translation_key_id' => $captionKeys[0]->id,
        ]);

        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $duplicatedGallery->id,
            'picture_id' => $pictures[1]->id,
            'order' => 2,
            'caption_translation_key_id' => null,
        ]);

        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $duplicatedGallery->id,
            'picture_id' => $pictures[2]->id,
            'order' => 3,
            'caption_translation_key_id' => $captionKeys[1]->id,
        ]);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_gallery_preserves_picture_order_in_pivot(): void
    {
        $parent = BlogPost::factory()->create();
        $pictures = Picture::factory()->count(5)->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 2,
            'pictures' => $pictures->pluck('id')->toArray(),
        ];

        $originalBlock = $this->service->createGalleryContent($parent, $galleryData, 1);
        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $duplicatedGallery = ContentGallery::find($duplicatedBlock->content_id);

        // Verify order is preserved for all pictures
        foreach ($pictures as $index => $picture) {
            $this->assertDatabaseHas('content_gallery_pictures', [
                'gallery_id' => $duplicatedGallery->id,
                'picture_id' => $picture->id,
                'order' => $index + 1,
            ]);
        }
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_gallery_preserves_caption_translation_keys(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $pictures = Picture::factory()->count(2)->create();
        $captionKey1 = TranslationKey::factory()->create();
        $captionKey2 = TranslationKey::factory()->create();

        $galleryData = [
            'layout' => 'list',
            'columns' => 1,
            'pictures' => $pictures->pluck('id')->toArray(),
        ];

        $originalBlock = $this->service->createGalleryContent($parent, $galleryData, 1);
        $originalGallery = ContentGallery::find($originalBlock->content_id);

        // Manually set caption translation keys
        $originalGallery->pictures()->updateExistingPivot($pictures[0]->id, [
            'caption_translation_key_id' => $captionKey1->id,
        ]);
        $originalGallery->pictures()->updateExistingPivot($pictures[1]->id, [
            'caption_translation_key_id' => $captionKey2->id,
        ]);
        $originalGallery->refresh();

        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $duplicatedGallery = ContentGallery::find($duplicatedBlock->content_id);

        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $duplicatedGallery->id,
            'picture_id' => $pictures[0]->id,
            'caption_translation_key_id' => $captionKey1->id,
        ]);

        $this->assertDatabaseHas('content_gallery_pictures', [
            'gallery_id' => $duplicatedGallery->id,
            'picture_id' => $pictures[1]->id,
            'caption_translation_key_id' => $captionKey2->id,
        ]);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_handles_gallery_without_pictures(): void
    {
        $parent = CreationDraft::factory()->create();

        $galleryData = [
            'layout' => 'carousel',
            'columns' => null,
        ];

        $originalBlock = $this->service->createGalleryContent($parent, $galleryData, 1);
        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $duplicatedGallery = ContentGallery::with('pictures')->find($duplicatedBlock->content_id);

        $this->assertEquals('carousel', $duplicatedGallery->layout);
        $this->assertNull($duplicatedGallery->columns);
        $this->assertCount(0, $duplicatedGallery->pictures);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_gallery_creates_separate_content_galleries_instance(): void
    {
        $parent = Creation::factory()->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
        ];

        $originalBlock = $this->service->createGalleryContent($parent, $galleryData, 1);
        $originalGalleryId = $originalBlock->content_id;

        $duplicatedBlock = $this->service->duplicateContent($originalBlock);
        $duplicatedGalleryId = $duplicatedBlock->content_id;

        $this->assertNotEquals($originalGalleryId, $duplicatedGalleryId);

        // Both galleries should exist
        $this->assertDatabaseHas('content_galleries', [
            'id' => $originalGalleryId,
        ]);
        $this->assertDatabaseHas('content_galleries', [
            'id' => $duplicatedGalleryId,
        ]);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicates_video_content(): void
    {
        $parent = BlogPost::factory()->create();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $originalBlock = $this->service->createVideoContent($parent, $video->id, 1, $captionKey->id);
        $originalVideoContent = ContentVideo::find($originalBlock->content_id);

        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        // New ContentVideo should be created
        $this->assertNotEquals($originalVideoContent->id, $duplicatedBlock->content_id);

        $duplicatedVideoContent = ContentVideo::find($duplicatedBlock->content_id);

        // Video ID and caption should be copied
        $this->assertEquals($video->id, $duplicatedVideoContent->video_id);
        $this->assertEquals($captionKey->id, $duplicatedVideoContent->caption_translation_key_id);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_video_copies_video_id(): void
    {
        $parent = CreationDraft::factory()->create();
        $video = Video::factory()->create();

        $originalBlock = $this->service->createVideoContent($parent, $video->id, 2);
        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $duplicatedVideoContent = ContentVideo::find($duplicatedBlock->content_id);

        $this->assertEquals($video->id, $duplicatedVideoContent->video_id);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_video_copies_caption_translation_key_id(): void
    {
        $parent = Creation::factory()->create();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $originalBlock = $this->service->createVideoContent($parent, $video->id, 1, $captionKey->id);
        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $duplicatedVideoContent = ContentVideo::find($duplicatedBlock->content_id);

        $this->assertEquals($captionKey->id, $duplicatedVideoContent->caption_translation_key_id);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_video_creates_separate_content_videos_instance(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $video = Video::factory()->create();

        $originalBlock = $this->service->createVideoContent($parent, $video->id, 1);
        $originalVideoContentId = $originalBlock->content_id;

        $duplicatedBlock = $this->service->duplicateContent($originalBlock);
        $duplicatedVideoContentId = $duplicatedBlock->content_id;

        $this->assertNotEquals($originalVideoContentId, $duplicatedVideoContentId);

        // Both video contents should exist
        $this->assertDatabaseHas('content_videos', [
            'id' => $originalVideoContentId,
        ]);
        $this->assertDatabaseHas('content_videos', [
            'id' => $duplicatedVideoContentId,
        ]);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_video_handles_null_caption(): void
    {
        $parent = CreationDraft::factory()->create();
        $video = Video::factory()->create();

        $originalBlock = $this->service->createVideoContent($parent, $video->id, 1, null);
        $duplicatedBlock = $this->service->duplicateContent($originalBlock);

        $duplicatedVideoContent = ContentVideo::find($duplicatedBlock->content_id);

        $this->assertNull($duplicatedVideoContent->caption_translation_key_id);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_uses_database_transaction(): void
    {
        $parent = BlogPost::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $originalBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->service->duplicateContent($originalBlock);
    }

    #[Test]
    #[Group('duplicate')]
    public function duplicate_uses_transaction(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $originalBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->service->duplicateContent($originalBlock);
    }

    #[Test]
    #[Group('validation')]
    public function validates_content_structure_returns_true_when_content_exists(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        $result = $this->service->validateContentStructure($parent);

        $this->assertTrue($result);
    }

    #[Test]
    #[Group('validation')]
    public function validates_content_structure_returns_false_when_no_content(): void
    {
        $parent = BlogPostDraft::factory()->create();

        $result = $this->service->validateContentStructure($parent);

        $this->assertFalse($result);
    }

    #[Test]
    #[Group('validation')]
    public function validates_content_structure_for_creation(): void
    {
        $parent = Creation::factory()->create();
        $video = Video::factory()->create();

        $this->service->createVideoContent($parent, $video->id, 1);

        $result = $this->service->validateContentStructure($parent);

        $this->assertTrue($result);
    }

    #[Test]
    #[Group('validation')]
    public function validates_content_structure_returns_false_for_creation_without_content(): void
    {
        $parent = Creation::factory()->create();

        $result = $this->service->validateContentStructure($parent);

        $this->assertFalse($result);
    }

    #[Test]
    #[Group('validation')]
    public function validates_content_structure_works_with_single_content_block(): void
    {
        $parent = CreationDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();

        $this->service->createMarkdownContent($parent, $translationKey->id, 1);

        $result = $this->service->validateContentStructure($parent);

        $this->assertTrue($result);
    }

    #[Test]
    #[Group('integration')]
    public function complex_workflow_create_reorder_validate(): void
    {
        $parent = BlogPostDraft::factory()->create();
        $translationKeys = TranslationKey::factory()->count(3)->create();
        $video = Video::factory()->create();

        // Create multiple content blocks
        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createVideoContent($parent, $video->id, 2);
        $block3 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 3);

        // Validate structure
        $this->assertTrue($this->service->validateContentStructure($parent));

        // Reorder
        $this->service->reorderContent($parent, [$block3->id, $block1->id, $block2->id]);

        // Verify order
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $block3->id,
            'order' => 1,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $block1->id,
            'order' => 2,
        ]);
        $this->assertDatabaseHas('blog_post_draft_contents', [
            'id' => $block2->id,
            'order' => 3,
        ]);

        // Still valid after reorder
        $this->assertTrue($this->service->validateContentStructure($parent));
    }

    #[Test]
    #[Group('integration')]
    public function duplicate_preserves_correct_order_after_multiple_operations(): void
    {
        $parent = CreationDraft::factory()->create();
        $translationKeys = TranslationKey::factory()->count(2)->create();

        // Create initial blocks
        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 2);

        // Duplicate block1
        $duplicatedBlock = $this->service->duplicateContent($block1);

        // Should be appended at the end
        $this->assertEquals(3, $duplicatedBlock->order);

        // Now reorder
        $this->service->reorderContent($parent, [$duplicatedBlock->id, $block1->id, $block2->id]);

        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $duplicatedBlock->id,
            'order' => 1,
        ]);
    }

    #[Test]
    #[Group('integration')]
    public function delete_content_does_not_affect_other_content_blocks(): void
    {
        $parent = Creation::factory()->create();
        $translationKeys = TranslationKey::factory()->count(3)->create();

        $block1 = $this->service->createMarkdownContent($parent, $translationKeys[0]->id, 1);
        $block2 = $this->service->createMarkdownContent($parent, $translationKeys[1]->id, 2);
        $block3 = $this->service->createMarkdownContent($parent, $translationKeys[2]->id, 3);

        // Delete middle block
        $this->service->deleteContent($block2);

        // Other blocks should still exist
        $this->assertDatabaseHas('creation_contents', [
            'id' => $block1->id,
        ]);
        $this->assertDatabaseHas('creation_contents', [
            'id' => $block3->id,
        ]);

        // Deleted block should be gone
        $this->assertDatabaseMissing('creation_contents', [
            'id' => $block2->id,
        ]);
    }

    #[Test]
    #[Group('integration')]
    public function gallery_sync_replaces_all_pictures_correctly(): void
    {
        $parent = BlogPost::factory()->create();
        $initialPictures = Picture::factory()->count(3)->create();

        $galleryData = [
            'layout' => 'grid',
            'columns' => 3,
            'pictures' => $initialPictures->pluck('id')->toArray(),
        ];

        $contentBlock = $this->service->createGalleryContent($parent, $galleryData, 1);
        $gallery = ContentGallery::find($contentBlock->content_id);

        // Update with completely different pictures
        $newPictures = Picture::factory()->count(2)->create();

        $this->service->updateGalleryContent($gallery, [
            'layout' => 'masonry',
            'columns' => 2,
            'pictures' => $newPictures->pluck('id')->toArray(),
        ]);

        // Old pictures should be detached
        foreach ($initialPictures as $pic) {
            $this->assertDatabaseMissing('content_gallery_pictures', [
                'gallery_id' => $gallery->id,
                'picture_id' => $pic->id,
            ]);
        }

        // New pictures should be attached
        foreach ($newPictures as $pic) {
            $this->assertDatabaseHas('content_gallery_pictures', [
                'gallery_id' => $gallery->id,
                'picture_id' => $pic->id,
            ]);
        }

        // Should have exactly 2 pictures
        $this->assertDatabaseCount('content_gallery_pictures', 2);
    }

    #[Test]
    #[Group('integration')]
    public function all_content_types_work_with_all_parent_types(): void
    {
        $parents = [
            BlogPostDraft::factory()->create(),
            BlogPost::factory()->create(),
            CreationDraft::factory()->create(),
            Creation::factory()->create(),
        ];

        foreach ($parents as $parent) {
            // Test markdown
            $translationKey = TranslationKey::factory()->create();
            $markdownBlock = $this->service->createMarkdownContent($parent, $translationKey->id, 1);
            $this->assertNotNull($markdownBlock);

            // Test gallery
            $galleryBlock = $this->service->createGalleryContent($parent, [
                'layout' => 'grid',
                'columns' => 2,
            ], 2);
            $this->assertNotNull($galleryBlock);

            // Test video
            $video = Video::factory()->create();
            $videoBlock = $this->service->createVideoContent($parent, $video->id, 3);
            $this->assertNotNull($videoBlock);

            // Validate
            $this->assertTrue($this->service->validateContentStructure($parent));
        }
    }
}
