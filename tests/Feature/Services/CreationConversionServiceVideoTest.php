<?php

namespace Tests\Feature\Services;

use App\Models\ContentMarkdown;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Video;
use App\Services\CreationConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreationConversionServiceVideoTest extends TestCase
{
    use RefreshDatabase;

    private CreationConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreationConversionService::class);
    }

    public function test_convert_draft_to_creation_copies_videos(): void
    {
        $draft = CreationDraft::factory()->create();

        // Add content block (required)
        $markdownContent = ContentMarkdown::factory()->create();
        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        $videos = Video::factory()->count(3)->create();

        $draft->videos()->sync($videos->pluck('id'));

        $creation = $this->service->convertDraftToCreation($draft);

        $this->assertInstanceOf(Creation::class, $creation);

        $creationVideoIds = $creation->videos()->pluck('videos.id')->sort()->values();
        $draftVideoIds = $draft->videos()->pluck('videos.id')->sort()->values();

        $this->assertEquals($draftVideoIds, $creationVideoIds);
        $this->assertCount(3, $creation->videos);
    }

    public function test_convert_draft_to_creation_with_no_videos(): void
    {
        $draft = CreationDraft::factory()->create();

        // Add content block (required)
        $markdownContent = ContentMarkdown::factory()->create();
        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        $creation = $this->service->convertDraftToCreation($draft);

        $this->assertInstanceOf(Creation::class, $creation);
        $this->assertCount(0, $creation->videos);
    }

    public function test_convert_draft_to_creation_updates_existing_creation_videos(): void
    {
        $existingCreation = Creation::factory()->create();
        $existingVideos = Video::factory()->count(2)->create();
        $existingCreation->videos()->sync($existingVideos->pluck('id'));

        $draft = CreationDraft::factory()->create([
            'original_creation_id' => $existingCreation->id,
        ]);

        // Add content block (required)
        $markdownContent = ContentMarkdown::factory()->create();
        $draft->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        $newVideos = Video::factory()->count(3)->create();
        $draft->videos()->sync($newVideos->pluck('id'));

        $updatedCreation = $this->service->convertDraftToCreation($draft);

        $this->assertEquals($existingCreation->id, $updatedCreation->id);

        $creationVideoIds = $updatedCreation->videos()->pluck('videos.id')->sort()->values();
        $draftVideoIds = $draft->videos()->pluck('videos.id')->sort()->values();

        $this->assertEquals($draftVideoIds, $creationVideoIds);
        $this->assertCount(3, $updatedCreation->videos);

        // Verify old videos are no longer attached
        foreach ($existingVideos as $video) {
            $this->assertFalse($updatedCreation->videos()->where('videos.id', $video->id)->exists());
        }
    }
}
