<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Content;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\Content\ContentUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContentUpdateService::class)]
class ContentUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentUpdateService::class);
    }

    #[Test]
    public function it_updates_markdown_content(): void
    {
        $oldTranslationKey = TranslationKey::factory()->create();
        $newTranslationKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $oldTranslationKey->id,
        ]);

        $updated = $this->service->updateMarkdown($markdown, $newTranslationKey->id);

        $this->assertEquals($newTranslationKey->id, $updated->translation_key_id);
        $this->assertDatabaseHas('content_markdowns', [
            'id' => $markdown->id,
            'translation_key_id' => $newTranslationKey->id,
        ]);
    }

    #[Test]
    public function it_returns_refreshed_markdown_after_update(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $newTranslationKey = TranslationKey::factory()->create();
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $translationKey->id,
        ]);

        $updated = $this->service->updateMarkdown($markdown, $newTranslationKey->id);

        $this->assertInstanceOf(ContentMarkdown::class, $updated);
        $this->assertEquals($newTranslationKey->id, $updated->translation_key_id);
    }

    #[Test]
    public function it_updates_gallery_layout(): void
    {
        $gallery = ContentGallery::factory()->create([
            'layout' => 'grid',
            'columns' => 3,
        ]);

        $updated = $this->service->updateGallery($gallery, [
            'layout' => 'masonry',
            'columns' => 4,
        ]);

        $this->assertEquals('masonry', $updated->layout);
        $this->assertEquals(4, $updated->columns);
    }

    #[Test]
    public function it_updates_gallery_with_null_columns(): void
    {
        $gallery = ContentGallery::factory()->create([
            'layout' => 'grid',
            'columns' => 3,
        ]);

        $updated = $this->service->updateGallery($gallery, [
            'layout' => 'slider',
        ]);

        $this->assertEquals('slider', $updated->layout);
        $this->assertNull($updated->columns);
    }

    #[Test]
    public function it_syncs_gallery_pictures(): void
    {
        $gallery = ContentGallery::factory()->withPictures(3)->create();
        $newPictures = Picture::factory()->count(2)->create();

        $updated = $this->service->updateGallery($gallery, [
            'layout' => 'grid',
            'pictures' => $newPictures->pluck('id')->toArray(),
        ]);

        $this->assertEquals(2, $updated->pictures()->count());
        $this->assertEquals(
            $newPictures->pluck('id')->sort()->values()->toArray(),
            $updated->pictures->pluck('id')->sort()->values()->toArray()
        );
    }

    #[Test]
    public function it_updates_gallery_picture_order(): void
    {
        $gallery = ContentGallery::factory()->create(['layout' => 'grid']);
        $pictures = Picture::factory()->count(3)->create();

        // First sync with initial order
        $this->service->updateGallery($gallery, [
            'layout' => 'grid',
            'pictures' => $pictures->pluck('id')->toArray(),
        ]);

        // Update with reversed order
        $reversedOrder = $pictures->pluck('id')->reverse()->values()->toArray();
        $updated = $this->service->updateGallery($gallery, [
            'layout' => 'grid',
            'pictures' => $reversedOrder,
        ]);

        $orderedPictures = $updated->pictures()->orderBy('content_gallery_pictures.order')->get();
        foreach ($orderedPictures as $index => $picture) {
            $this->assertEquals($reversedOrder[$index], $picture->id);
            $this->assertEquals($index + 1, $picture->pivot->order);
        }
    }

    #[Test]
    public function it_clears_gallery_pictures_when_empty_array_passed(): void
    {
        $gallery = ContentGallery::factory()->withPictures(3)->create();

        $updated = $this->service->updateGallery($gallery, [
            'layout' => 'grid',
            'pictures' => [],
        ]);

        $this->assertEquals(0, $updated->pictures()->count());
    }

    #[Test]
    public function it_updates_video_content(): void
    {
        $oldVideo = Video::factory()->create();
        $newVideo = Video::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $oldVideo->id,
        ]);

        $updated = $this->service->updateVideo($videoContent, $newVideo->id);

        $this->assertEquals($newVideo->id, $updated->video_id);
        $this->assertDatabaseHas('content_videos', [
            'id' => $videoContent->id,
            'video_id' => $newVideo->id,
        ]);
    }

    #[Test]
    public function it_updates_video_caption(): void
    {
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => null,
        ]);

        $updated = $this->service->updateVideo($videoContent, $video->id, $captionKey->id);

        $this->assertEquals($captionKey->id, $updated->caption_translation_key_id);
    }

    #[Test]
    public function it_removes_video_caption_when_null_passed(): void
    {
        $video = Video::factory()->create();
        $captionKey = TranslationKey::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $updated = $this->service->updateVideo($videoContent, $video->id, null);

        $this->assertNull($updated->caption_translation_key_id);
    }

    #[Test]
    public function it_returns_refreshed_video_after_update(): void
    {
        $video = Video::factory()->create();
        $newVideo = Video::factory()->create();
        $videoContent = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        $updated = $this->service->updateVideo($videoContent, $newVideo->id);

        $this->assertInstanceOf(ContentVideo::class, $updated);
        $this->assertEquals($newVideo->id, $updated->video_id);
    }
}
