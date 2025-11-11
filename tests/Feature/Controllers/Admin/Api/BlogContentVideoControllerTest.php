<?php

namespace Tests\Feature\Controllers\Admin\Api;

use App\Http\Controllers\Admin\Api\ContentVideoController;
use App\Models\ContentVideo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(ContentVideoController::class)]
class BlogContentVideoControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_store_creates_video_content_with_video_and_caption()
    {
        $video = Video::factory()->create();

        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => $video->id,
            'caption' => 'Test video caption',
            'locale' => 'fr',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'video_id',
                'caption_translation_key_id',
                'created_at',
                'updated_at',
                'video',
                'caption_translation_key' => [
                    'translations' => [
                        '*' => ['locale', 'text'],
                    ],
                ],
            ]);

        $videoContent = ContentVideo::first();
        $this->assertNotNull($videoContent);
        $this->assertEquals($video->id, $videoContent->video_id);
        $this->assertNotNull($videoContent->caption_translation_key_id);

        $this->assertDatabaseHas('translation_keys', [
            'id' => $videoContent->caption_translation_key_id,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'fr',
            'text' => 'Test video caption',
            'translation_key_id' => $videoContent->caption_translation_key_id,
        ]);

        $this->assertDatabaseHas('translations', [
            'locale' => 'en',
            'text' => '',
            'translation_key_id' => $videoContent->caption_translation_key_id,
        ]);
    }

    #[Test]
    public function test_store_creates_video_content_with_only_video_id()
    {
        $video = Video::factory()->create();

        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => $video->id,
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $videoContent = ContentVideo::first();
        $this->assertNotNull($videoContent);
        $this->assertEquals($video->id, $videoContent->video_id);
        $this->assertNull($videoContent->caption_translation_key_id);
    }

    #[Test]
    public function test_store_creates_video_content_with_null_caption()
    {
        $video = Video::factory()->create();

        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => $video->id,
            'caption' => null,
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $videoContent = ContentVideo::first();
        $this->assertNotNull($videoContent);
        $this->assertEquals($video->id, $videoContent->video_id);
        $this->assertNull($videoContent->caption_translation_key_id);
    }

    #[Test]
    public function test_store_creates_video_content_with_empty_caption()
    {
        $video = Video::factory()->create();

        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => $video->id,
            'caption' => '',
            'locale' => 'en',
        ]);

        $response->assertCreated();

        $videoContent = ContentVideo::first();
        $this->assertNotNull($videoContent);
        $this->assertEquals($video->id, $videoContent->video_id);
        $this->assertNull($videoContent->caption_translation_key_id);
    }

    #[Test]
    public function test_store_creates_video_content_without_video_id()
    {
        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'caption' => 'Caption without video',
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        $videoContent = ContentVideo::first();
        $this->assertNotNull($videoContent);
        $this->assertNull($videoContent->video_id);
        $this->assertNotNull($videoContent->caption_translation_key_id);
    }

    #[Test]
    public function test_store_validates_missing_locale()
    {
        $video = Video::factory()->create();

        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => $video->id,
            'caption' => 'Test caption',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function test_store_validates_invalid_locale()
    {
        $video = Video::factory()->create();

        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => $video->id,
            'caption' => 'Test caption',
            'locale' => 'es',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function test_store_validates_invalid_video_id()
    {
        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => 99999,
            'caption' => 'Test caption',
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video_id']);
    }

    #[Test]
    public function test_store_validates_caption_too_long()
    {
        $video = Video::factory()->create();

        $response = $this->postJson('/dashboard/api/blog-content-video', [
            'video_id' => $video->id,
            'caption' => str_repeat('a', 501),
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['caption']);
    }

    #[Test]
    public function test_show_returns_video_content_with_relations()
    {
        $videoContent = ContentVideo::factory()->withCaption()->create();

        $response = $this->getJson("/dashboard/api/blog-content-video/{$videoContent->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'video_id',
                'caption_translation_key_id',
                'created_at',
                'updated_at',
                'video',
                'caption_translation_key' => [
                    'id',
                    'key',
                    'translations' => [
                        '*' => ['id', 'locale', 'text'],
                    ],
                ],
            ])
            ->assertJson([
                'id' => $videoContent->id,
                'video_id' => $videoContent->video_id,
            ]);
    }

    #[Test]
    public function test_show_returns_404_for_non_existent_content()
    {
        $response = $this->getJson('/dashboard/api/blog-content-video/99999');

        $response->assertNotFound();
    }

    #[Test]
    public function test_update_modifies_video_id()
    {
        $videoContent = ContentVideo::factory()->create();
        $newVideo = Video::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'video_id' => $newVideo->id,
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $videoContent->refresh();
        $this->assertEquals($newVideo->id, $videoContent->video_id);
    }

    #[Test]
    public function test_update_adds_caption_to_content_without_caption()
    {
        $videoContent = ContentVideo::factory()->withoutCaption()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => 'New caption added',
            'locale' => 'fr',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'caption_translation_key' => [
                    'translations',
                ],
            ]);

        $videoContent->refresh();
        $this->assertNotNull($videoContent->caption_translation_key_id);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $videoContent->caption_translation_key_id,
            'locale' => 'fr',
            'text' => 'New caption added',
        ]);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $videoContent->caption_translation_key_id,
            'locale' => 'en',
            'text' => '',
        ]);
    }

    #[Test]
    public function test_update_modifies_existing_caption_french()
    {
        $videoContent = ContentVideo::factory()->withCaption()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => 'Updated French caption',
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $videoContent->caption_translation_key_id,
            'locale' => 'fr',
            'text' => 'Updated French caption',
        ]);
    }

    #[Test]
    public function test_update_modifies_existing_caption_english()
    {
        $videoContent = ContentVideo::factory()->withCaption()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => 'Updated English caption',
            'locale' => 'en',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $videoContent->caption_translation_key_id,
            'locale' => 'en',
            'text' => 'Updated English caption',
        ]);
    }

    #[Test]
    public function test_update_removes_caption_with_empty_string()
    {
        $videoContent = ContentVideo::factory()->withCaption()->create();
        $translationKeyId = $videoContent->caption_translation_key_id;

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => '',
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $videoContent->refresh();
        $this->assertNull($videoContent->caption_translation_key_id);

        $this->assertDatabaseMissing('translation_keys', [
            'id' => $translationKeyId,
        ]);

        $this->assertDatabaseMissing('translations', [
            'translation_key_id' => $translationKeyId,
        ]);
    }

    #[Test]
    public function test_update_removes_caption_with_null()
    {
        $videoContent = ContentVideo::factory()->withCaption()->create();
        $translationKeyId = $videoContent->caption_translation_key_id;

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => null,
            'locale' => 'fr',
        ]);

        $response->assertOk();

        $videoContent->refresh();
        $this->assertNull($videoContent->caption_translation_key_id);

        $this->assertDatabaseMissing('translation_keys', [
            'id' => $translationKeyId,
        ]);

        $this->assertDatabaseMissing('translations', [
            'translation_key_id' => $translationKeyId,
        ]);
    }

    #[Test]
    public function test_update_validates_missing_locale()
    {
        $videoContent = ContentVideo::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => 'Test caption',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale'])
            ->assertJson([
                'message' => 'Validation failed',
            ]);
    }

    #[Test]
    public function test_update_validates_invalid_locale()
    {
        $videoContent = ContentVideo::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => 'Test caption',
            'locale' => 'de',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }

    #[Test]
    public function test_update_validates_invalid_video_id()
    {
        $videoContent = ContentVideo::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'video_id' => 99999,
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video_id']);
    }

    #[Test]
    public function test_update_validates_caption_too_long()
    {
        $videoContent = ContentVideo::factory()->create();

        $response = $this->putJson("/dashboard/api/blog-content-video/{$videoContent->id}", [
            'caption' => str_repeat('a', 501),
            'locale' => 'fr',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['caption']);
    }

    #[Test]
    public function test_update_returns_404_for_non_existent_content()
    {
        $response = $this->putJson('/dashboard/api/blog-content-video/99999', [
            'caption' => 'Test caption',
            'locale' => 'fr',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function test_destroy_deletes_video_content_with_caption()
    {
        $videoContent = ContentVideo::factory()->withCaption()->create();
        $translationKeyId = $videoContent->caption_translation_key_id;

        $response = $this->deleteJson("/dashboard/api/blog-content-video/{$videoContent->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Video content deleted successfully',
            ]);

        $this->assertDatabaseMissing('content_videos', [
            'id' => $videoContent->id,
        ]);

        $this->assertDatabaseMissing('translation_keys', [
            'id' => $translationKeyId,
        ]);

        $this->assertDatabaseMissing('translations', [
            'translation_key_id' => $translationKeyId,
        ]);
    }

    #[Test]
    public function test_destroy_deletes_video_content_without_caption()
    {
        $videoContent = ContentVideo::factory()->withoutCaption()->create();

        $response = $this->deleteJson("/dashboard/api/blog-content-video/{$videoContent->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Video content deleted successfully',
            ]);

        $this->assertDatabaseMissing('content_videos', [
            'id' => $videoContent->id,
        ]);
    }

    #[Test]
    public function test_destroy_returns_404_for_non_existent_content()
    {
        $response = $this->deleteJson('/dashboard/api/blog-content-video/99999');

        $response->assertNotFound();
    }
}
