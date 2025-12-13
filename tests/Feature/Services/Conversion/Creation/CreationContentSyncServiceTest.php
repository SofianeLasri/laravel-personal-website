<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\Creation;

use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\CreationDraftContent;
use App\Models\CreationDraftFeature;
use App\Models\CreationDraftScreenshot;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Screenshot;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\Video;
use App\Services\Conversion\Creation\CreationContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationContentSyncService::class)]
class CreationContentSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreationContentSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreationContentSyncService::class);
    }

    #[Test]
    public function it_syncs_technologies_relationship(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $technologies = Technology::factory()->count(3)->create();
        $draft->technologies()->attach($technologies);

        $this->service->syncRelationships($draft, $creation);

        $this->assertEquals(3, $creation->technologies()->count());
    }

    #[Test]
    public function it_syncs_people_relationship(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $people = Person::factory()->count(2)->create();
        $draft->people()->attach($people);

        $this->service->syncRelationships($draft, $creation);

        $this->assertEquals(2, $creation->people()->count());
    }

    #[Test]
    public function it_syncs_tags_relationship(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $tags = Tag::factory()->count(4)->create();
        $draft->tags()->attach($tags);

        $this->service->syncRelationships($draft, $creation);

        $this->assertEquals(4, $creation->tags()->count());
    }

    #[Test]
    public function it_syncs_videos_relationship(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $videos = Video::factory()->count(2)->create();
        $draft->videos()->attach($videos);

        $this->service->syncRelationships($draft, $creation);

        $this->assertEquals(2, $creation->videos()->count());
    }

    #[Test]
    public function it_creates_features_from_draft(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        CreationDraftFeature::factory()->count(3)->create([
            'creation_draft_id' => $draft->id,
        ]);

        $this->service->createFeatures($draft, $creation);

        $this->assertEquals(3, $creation->features()->count());
    }

    #[Test]
    public function it_preserves_feature_translation_keys(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $draftFeature = CreationDraftFeature::factory()->create([
            'creation_draft_id' => $draft->id,
        ]);

        $this->service->createFeatures($draft, $creation);

        $feature = $creation->features()->first();
        $this->assertEquals($draftFeature->title_translation_key_id, $feature->title_translation_key_id);
        $this->assertEquals($draftFeature->description_translation_key_id, $feature->description_translation_key_id);
    }

    #[Test]
    public function it_recreates_features_deleting_existing(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        Feature::factory()->count(2)->create([
            'creation_id' => $creation->id,
        ]);
        CreationDraftFeature::factory()->count(3)->create([
            'creation_draft_id' => $draft->id,
        ]);

        $this->service->recreateFeatures($draft, $creation);

        $this->assertEquals(3, $creation->features()->count());
    }

    #[Test]
    public function it_creates_screenshots_from_draft(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        CreationDraftScreenshot::factory()
            ->count(4)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create([
                'creation_draft_id' => $draft->id,
            ]);

        $this->service->createScreenshots($draft, $creation);

        $this->assertEquals(4, $creation->screenshots()->count());
    }

    #[Test]
    public function it_preserves_screenshot_order(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 1,
        ]);
        CreationDraftScreenshot::factory()->create([
            'creation_draft_id' => $draft->id,
            'order' => 2,
        ]);

        $this->service->createScreenshots($draft, $creation);

        $screenshots = $creation->screenshots()->orderBy('order')->get();
        $this->assertEquals(1, $screenshots[0]->order);
        $this->assertEquals(2, $screenshots[1]->order);
    }

    #[Test]
    public function it_recreates_screenshots_deleting_existing(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        Screenshot::factory()
            ->count(5)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create([
                'creation_id' => $creation->id,
            ]);
        CreationDraftScreenshot::factory()
            ->count(2)
            ->sequence(fn ($sequence) => ['order' => $sequence->index + 1])
            ->create([
                'creation_draft_id' => $draft->id,
            ]);

        $this->service->recreateScreenshots($draft, $creation);

        $this->assertEquals(2, $creation->screenshots()->count());
    }

    #[Test]
    public function it_creates_markdown_contents_from_draft(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $markdown = ContentMarkdown::factory()->create();
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $this->service->createContents($draft, $creation);

        $this->assertEquals(1, $creation->contents()->count());
    }

    #[Test]
    public function it_creates_gallery_contents_from_draft(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $gallery = ContentGallery::factory()->create();
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $this->service->createContents($draft, $creation);

        $this->assertEquals(1, $creation->contents()->count());
    }

    #[Test]
    public function it_creates_video_contents_from_draft(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $video = ContentVideo::factory()->create();
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $video->id,
            'order' => 1,
        ]);

        $this->service->createContents($draft, $creation);

        $this->assertEquals(1, $creation->contents()->count());
    }

    #[Test]
    public function it_preserves_content_order(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $markdown1 = ContentMarkdown::factory()->create();
        $markdown2 = ContentMarkdown::factory()->create();
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown1->id,
            'order' => 1,
        ]);
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown2->id,
            'order' => 2,
        ]);

        $this->service->createContents($draft, $creation);

        $contents = $creation->contents()->orderBy('order')->get();
        $this->assertEquals(1, $contents[0]->order);
        $this->assertEquals(2, $contents[1]->order);
    }

    #[Test]
    public function it_recreates_contents_deleting_existing(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $oldMarkdown = ContentMarkdown::factory()->create();
        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $oldMarkdown->id,
            'order' => 1,
        ]);
        $newMarkdown = ContentMarkdown::factory()->create();
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $newMarkdown->id,
            'order' => 1,
        ]);

        $this->service->recreateContents($draft, $creation);

        $this->assertEquals(1, $creation->contents()->count());
        $this->assertNull(ContentMarkdown::find($oldMarkdown->id));
    }

    #[Test]
    public function it_cleans_up_gallery_pictures_when_recreating(): void
    {
        $draft = CreationDraft::factory()->create();
        $creation = Creation::factory()->create();
        $oldGallery = ContentGallery::factory()->withPictures(2)->create();
        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $oldGallery->id,
            'order' => 1,
        ]);
        $newMarkdown = ContentMarkdown::factory()->create();
        CreationDraftContent::factory()->create([
            'creation_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $newMarkdown->id,
            'order' => 1,
        ]);

        $this->service->recreateContents($draft, $creation);

        $this->assertNull(ContentGallery::find($oldGallery->id));
    }
}
