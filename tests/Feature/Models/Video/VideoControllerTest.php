<?php

namespace Tests\Feature\Models\Video;

use App\Enums\VideoVisibility;
use App\Http\Controllers\Admin\Api\VideoController;
use App\Models\Picture;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(VideoController::class)]
class VideoControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->allows([
                    'uploadVideo' => [
                        'guid' => 'bunny-12345',
                        'title' => 'Test Video',
                        'size' => 123456,
                        'created_at' => now(),
                    ],
                    'getVideo' => [
                        'guid' => 'bunny-12345',
                        'title' => 'Test Video',
                        'size' => 123456,
                        'created_at' => now(),
                    ],
                    'deleteVideo' => true,
                    'getPlaybackUrl' => 'https://example.com/playback/bunny-12345',
                    'getThumbnailUrl' => 'https://example.com/thumbnail/bunny-12345',
                    'isVideoReady' => true,
                ]);
            })
        );
    }

    #[Test]
    public function test_index_with_videos(): void
    {
        $videos = Video::factory()->count(3)->create();

        $response = $this->getJson(route('dashboard.api.videos.index'));

        $response->assertOk()
            ->assertJson($videos->toArray());
    }

    #[Test]
    public function test_index_without_videos(): void
    {
        $response = $this->getJson(route('dashboard.api.videos.index'));

        $response->assertOk()
            ->assertJson([]);
    }

    #[Test]
    public function test_store_video_with_valid_data(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $response = $this->json('POST', route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'name',
                'path',
                'cover_picture_id',
                'bunny_video_id',
                'created_at',
                'updated_at',
            ]);

        Storage::disk('local')->assertExists($response->json('path'));
    }

    #[Test]
    public function test_store_video_validation_succeed_with_missing_name(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $videoData = [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'cover_picture_id' => $picture->id,
        ];

        $response = $this->postJson(route('dashboard.api.videos.store'), $videoData);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'name',
                'path',
                'cover_picture_id',
                'bunny_video_id',
                'created_at',
                'updated_at',
            ]);
    }

    #[Test]
    public function test_store_video_validation_fails_with_invalid_cover_picture_id(): void
    {
        Storage::fake('local');

        $videoData = [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => 99999,
            'bunny_video_id' => 'bunny-12345',
        ];

        $response = $this->postJson(route('dashboard.api.videos.store'), $videoData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cover_picture_id');
    }

    #[Test]
    public function test_store_video_validation_fails_with_missing_vide(): void
    {
        $picture = Picture::factory()->create();

        $videoData = [
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $response = $this->postJson(route('dashboard.api.videos.store'), $videoData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('video');
    }

    #[Test]
    public function test_show_video(): void
    {
        $video = Video::factory()->create();

        $response = $this->getJson(route('dashboard.api.videos.show', $video->id));

        $response->assertOk()
            ->assertJson($video->toArray());
    }

    #[Test]
    public function test_show_video_not_found(): void
    {
        $response = $this->getJson(route('dashboard.api.videos.show', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_update_video_with_valid_data(): void
    {
        $video = Video::factory()->readyAndPublic()->create();
        $newPicture = Picture::factory()->create();

        $updateData = [
            'name' => 'Updated Video',
            'cover_picture_id' => $newPicture->id,
            'visibility' => $video->visibility,
        ];

        $response = $this->putJson(route('dashboard.api.videos.update', $video->id), $updateData);

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'path',
                'cover_picture_id',
                'bunny_video_id',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            ...$updateData,
        ]);
    }

    #[Test]
    public function test_update_video_validation_fails(): void
    {
        $video = Video::factory()->create();

        $updateData = [
            'name' => '',
            'cover_picture_id' => 99999,
        ];

        $response = $this->putJson(route('dashboard.api.videos.update', $video->id), $updateData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'cover_picture_id']);
    }

    #[Test]
    public function test_update_video_not_found()
    {
        $picture = Picture::factory()->create();

        $updateData = [
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
            'visibility' => VideoVisibility::PUBLIC,
        ];

        $response = $this->putJson(route('dashboard.api.videos.update', 99999), $updateData);

        $response->assertNotFound();
    }

    #[Test]
    public function test_destroy_video(): void
    {
        $video = Video::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.videos.destroy', $video->id));

        $response->assertNoContent();

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    #[Test]
    public function test_destroy_video_not_found(): void
    {
        $response = $this->deleteJson(route('dashboard.api.videos.destroy', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_update_returns_updated_video(): void
    {
        $video = Video::factory()->readyAndPublic()->create();
        $newPicture = Picture::factory()->create();

        $updateData = [
            'name' => 'Updated Video',
            'cover_picture_id' => $newPicture->id,
            'visibility' => VideoVisibility::PUBLIC,
        ];

        $response = $this->putJson(route('dashboard.api.videos.update', $video->id), $updateData);

        $response->assertOk();

        $responseData = $response->json();
        $this->assertEquals($updateData['name'], $responseData['name']);
        $this->assertEquals($updateData['cover_picture_id'], $responseData['cover_picture_id']);
    }

    #[Test]
    public function test_cannot_change_visibility_to_public_if_video_is_not_transcoded()
    {
        $video = Video::factory()->transcodingAndPrivate()->create();

        $response = $this->putJson(route('dashboard.api.videos.update', $video->id), [
            'name' => $video->name,
            'cover_picture_id' => $video->cover_picture_id,
            'visibility' => VideoVisibility::PUBLIC,
        ]);

        $response->assertConflict() // 409
            ->assertJson(['error' => 'Cannot set visibility to public until video is ready.']);
    }
}
