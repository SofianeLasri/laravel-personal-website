<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\Conversion\Creation\DraftToCreationConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreationContentBlocksIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private DraftToCreationConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = app(DraftToCreationConverter::class);
    }

    #[Test]
    public function it_converts_draft_with_markdown_content_to_creation(): void
    {
        $draft = CreationDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $translationKey->id,
        ]);

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $creation = $this->converter->convert($draft);

        $this->assertInstanceOf(Creation::class, $creation);
        $this->assertCount(1, $creation->contents);

        $content = $creation->contents->first();
        $this->assertEquals(ContentMarkdown::class, $content->content_type);
        $this->assertNotEquals($markdown->id, $content->content_id);
        $this->assertEquals(1, $content->order);

        $this->assertInstanceOf(ContentMarkdown::class, $content->content);
        // Verify translation key was duplicated (new ID, same content)
        $this->assertNotEquals($translationKey->id, $content->content->translation_key_id);
        $this->assertNotNull($content->content->translationKey);
    }

    #[Test]
    public function it_converts_draft_with_multiple_content_blocks_to_creation(): void
    {
        $draft = CreationDraft::factory()->create();

        $markdown = ContentMarkdown::factory()->create();
        $gallery = ContentGallery::factory()->create();
        $videoContent = ContentVideo::factory()->create();

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $draft->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 2,
        ]);

        $draft->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 3,
        ]);

        $creation = $this->converter->convert($draft);

        $this->assertCount(3, $creation->contents);

        $contents = $creation->contents->sortBy('order')->values();

        $this->assertEquals(ContentMarkdown::class, $contents[0]->content_type);
        $this->assertEquals(1, $contents[0]->order);

        $this->assertEquals(ContentGallery::class, $contents[1]->content_type);
        $this->assertEquals(2, $contents[1]->order);

        $this->assertEquals(ContentVideo::class, $contents[2]->content_type);
        $this->assertEquals(3, $contents[2]->order);
    }

    #[Test]
    public function it_updates_existing_creation_with_new_content_blocks(): void
    {
        $creation = Creation::factory()->create();
        $oldMarkdown = ContentMarkdown::factory()->create();

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $oldMarkdown->id,
            'order' => 1,
        ]);

        $draft = CreationDraft::fromCreation($creation);
        $newMarkdown = ContentMarkdown::factory()->create();
        $newGallery = ContentGallery::factory()->create();

        $draft->contents()->delete();

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $newMarkdown->id,
            'order' => 1,
        ]);

        $draft->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $newGallery->id,
            'order' => 2,
        ]);

        $updatedCreation = $this->converter->convert($draft);

        $this->assertEquals($creation->id, $updatedCreation->id);
        $this->assertCount(2, $updatedCreation->contents);

        // Old content should be removed
        $this->assertDatabaseMissing('creation_contents', [
            'content_type' => ContentMarkdown::class,
            'content_id' => $oldMarkdown->id,
        ]);
    }

    #[Test]
    public function it_converts_draft_with_gallery_containing_pictures(): void
    {
        $draft = CreationDraft::factory()->create();
        $gallery = ContentGallery::factory()->create(['layout' => 'grid', 'columns' => 3]);
        $pictures = Picture::factory()->count(3)->create();

        $pictureData = [];
        foreach ($pictures as $index => $picture) {
            $pictureData[$picture->id] = [
                'order' => $index + 1,
                'caption_translation_key_id' => TranslationKey::factory()->create()->id,
            ];
        }
        $gallery->pictures()->attach($pictureData);

        $draft->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $creation = $this->converter->convert($draft);

        $this->assertCount(1, $creation->contents);

        $content = $creation->contents->first();
        $this->assertInstanceOf(ContentGallery::class, $content->content);

        $createdGallery = $content->content;
        $this->assertEquals('grid', $createdGallery->layout);
        $this->assertEquals(3, $createdGallery->columns);
        $this->assertCount(3, $createdGallery->pictures);
    }

    #[Test]
    public function it_converts_draft_with_video_content_and_caption(): void
    {
        $draft = CreationDraft::factory()->create();
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();

        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $draft->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $creation = $this->converter->convert($draft);

        $this->assertCount(1, $creation->contents);

        $content = $creation->contents->first();
        $this->assertInstanceOf(ContentVideo::class, $content->content);
        $this->assertNotEquals($videoContent->id, $content->content_id);

        $createdVideo = $content->content;
        $this->assertEquals($video->id, $createdVideo->video_id);
        // Caption key should be duplicated (new ID, same content)
        $this->assertNotEquals($captionKey->id, $createdVideo->caption_translation_key_id);
        $this->assertNotNull($createdVideo->captionTranslationKey);
    }

    #[Test]
    public function it_preserves_content_order_during_conversion(): void
    {
        $draft = CreationDraft::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $markdown = ContentMarkdown::factory()->create();
            $draft->contents()->create([
                'content_type' => ContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => $i,
            ]);
        }

        $creation = $this->converter->convert($draft);

        $this->assertCount(5, $creation->contents);

        $contents = $creation->contents->sortBy('order')->values();

        foreach ($contents as $index => $content) {
            $this->assertEquals($index + 1, $content->order);
        }
    }

    #[Test]
    public function it_creates_independent_content_instances_during_conversion(): void
    {
        $draft = CreationDraft::factory()->create();
        $translationKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $translationKey->id,
        ]);

        $draftContent = $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $creation = $this->converter->convert($draft);

        $creationContent = $creation->contents->first();

        // Content markdown ID should be different (duplicated)
        $this->assertNotEquals($markdown->id, $creationContent->content_id);

        // Translation key should also be duplicated (new ID, same content)
        $this->assertNotEquals($translationKey->id, $creationContent->content->translation_key_id);
        $this->assertNotNull($creationContent->content->translationKey);

        // Verify draft content still exists in database
        $this->assertDatabaseHas('creation_draft_contents', [
            'id' => $draftContent->id,
            'creation_draft_id' => $draft->id,
        ]);
    }

    #[Test]
    public function it_handles_empty_content_blocks_list(): void
    {
        $draft = CreationDraft::factory()->create();
        // No content blocks added

        $creation = $this->converter->convert($draft);

        $this->assertInstanceOf(Creation::class, $creation);
        $this->assertCount(0, $creation->contents);
    }

    #[Test]
    public function it_deletes_old_content_blocks_when_updating_creation(): void
    {
        $creation = Creation::factory()->create();

        $oldMarkdown1 = ContentMarkdown::factory()->create();
        $oldMarkdown2 = ContentMarkdown::factory()->create();
        $oldGallery = ContentGallery::factory()->create();

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $oldMarkdown1->id,
            'order' => 1,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $oldMarkdown2->id,
            'order' => 2,
        ]);

        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $oldGallery->id,
            'order' => 3,
        ]);

        $draft = CreationDraft::fromCreation($creation);
        $draft->contents()->delete();

        $newMarkdown = ContentMarkdown::factory()->create();
        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $newMarkdown->id,
            'order' => 1,
        ]);

        $updatedCreation = $this->converter->convert($draft);

        $this->assertEquals($creation->id, $updatedCreation->id);
        $this->assertCount(1, $updatedCreation->contents);

        // Verify all old content blocks are deleted
        $this->assertDatabaseMissing('creation_contents', [
            'creation_id' => $creation->id,
            'content_id' => $oldMarkdown1->id,
        ]);

        $this->assertDatabaseMissing('creation_contents', [
            'creation_id' => $creation->id,
            'content_id' => $oldMarkdown2->id,
        ]);

        $this->assertDatabaseMissing('creation_contents', [
            'creation_id' => $creation->id,
            'content_id' => $oldGallery->id,
        ]);
    }

    #[Test]
    public function it_converts_complex_draft_with_all_content_types(): void
    {
        $draft = CreationDraft::factory()->create();

        // Markdown content
        $translationKey1 = TranslationKey::factory()->create();
        $translationKey2 = TranslationKey::factory()->create();
        $markdown1 = ContentMarkdown::factory()->create(['translation_key_id' => $translationKey1->id]);
        $markdown2 = ContentMarkdown::factory()->create(['translation_key_id' => $translationKey2->id]);

        // Gallery with pictures
        $gallery = ContentGallery::factory()->create(['layout' => 'carousel']);
        $pictures = Picture::factory()->count(2)->create();
        $gallery->pictures()->attach($pictures->pluck('id')->toArray());

        // Video with caption
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown1->id,
            'order' => 1,
        ]);

        $draft->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 2,
        ]);

        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 3,
        ]);

        $draft->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 4,
        ]);

        $creation = $this->converter->convert($draft);

        $this->assertCount(4, $creation->contents);

        $contents = $creation->contents->sortBy('order')->values();

        // Verify first markdown
        $this->assertEquals(ContentMarkdown::class, $contents[0]->content_type);
        $this->assertEquals(1, $contents[0]->order);
        // Translation key duplicated
        $this->assertNotEquals($translationKey1->id, $contents[0]->content->translation_key_id);
        $this->assertNotNull($contents[0]->content->translationKey);

        // Verify gallery
        $this->assertEquals(ContentGallery::class, $contents[1]->content_type);
        $this->assertEquals(2, $contents[1]->order);
        $this->assertEquals('carousel', $contents[1]->content->layout);
        $this->assertCount(2, $contents[1]->content->pictures);

        // Verify second markdown
        $this->assertEquals(ContentMarkdown::class, $contents[2]->content_type);
        $this->assertEquals(3, $contents[2]->order);
        // Translation key duplicated
        $this->assertNotEquals($translationKey2->id, $contents[2]->content->translation_key_id);
        $this->assertNotNull($contents[2]->content->translationKey);

        // Verify video
        $this->assertEquals(ContentVideo::class, $contents[3]->content_type);
        $this->assertEquals(4, $contents[3]->order);
        $this->assertEquals($video->id, $contents[3]->content->video_id);
        // Caption key duplicated
        $this->assertNotEquals($captionKey->id, $contents[3]->content->caption_translation_key_id);
        $this->assertNotNull($contents[3]->content->captionTranslationKey);
    }
}
