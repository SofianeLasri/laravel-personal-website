<?php

namespace Tests\Feature\Models\Video;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Controllers\Admin\Api\VideoController;
use App\Jobs\PictureJob;
use App\Models\Picture;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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
                        'status' => 1,
                    ],
                    'getVideo' => [
                        'guid' => 'bunny-12345',
                        'title' => 'Test Video',
                        'length' => 120,
                        'width' => 1920,
                        'height' => 1080,
                        'storageSize' => 1024000,
                        'status' => 4,
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

    #[Test]
    public function test_metadata_returns_video_metadata(): void
    {
        $video = Video::factory()->create();
        $expectedMetadata = [
            'duration' => 120,
            'width' => 1920,
            'height' => 1080,
            'size' => 1024000,
        ];

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) use ($expectedMetadata) {
                $mock->shouldReceive('getVideoMetadata')
                    ->once()
                    ->andReturn($expectedMetadata);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.metadata', $video->id));

        $response->assertOk()
            ->assertJson($expectedMetadata);
    }

    #[Test]
    public function test_metadata_returns_404_when_metadata_not_found(): void
    {
        $video = Video::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('getVideoMetadata')
                    ->once()
                    ->andReturn(null);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.metadata', $video->id));

        $response->assertNotFound()
            ->assertJson(['message' => 'Video metadata not found.']);
    }

    #[Test]
    public function test_metadata_returns_404_when_video_not_found(): void
    {
        $response = $this->getJson(route('dashboard.api.videos.metadata', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_status_returns_video_status(): void
    {
        $video = Video::factory()->create();
        $bunnyData = [
            'status' => 4,
        ];

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) use ($bunnyData) {
                $mock->shouldReceive('isVideoReady')
                    ->once()
                    ->andReturn(true);
                $mock->shouldReceive('getVideo')
                    ->once()
                    ->andReturn($bunnyData);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.status', $video->id));

        $response->assertOk()
            ->assertJson([
                'is_ready' => true,
                'status' => 4,
                'status_text' => 'Finished',
            ]);
    }

    #[Test]
    public function test_status_handles_created_status(): void
    {
        $video = Video::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('isVideoReady')->andReturn(false);
                $mock->shouldReceive('getVideo')->andReturn(['status' => 0]);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.status', $video->id));

        $response->assertOk()
            ->assertJson([
                'is_ready' => false,
                'status' => 0,
                'status_text' => 'Created',
            ]);
    }

    #[Test]
    public function test_status_handles_uploaded_status(): void
    {
        $video = Video::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('isVideoReady')->andReturn(false);
                $mock->shouldReceive('getVideo')->andReturn(['status' => 1]);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.status', $video->id));

        $response->assertOk()
            ->assertJson([
                'is_ready' => false,
                'status' => 1,
                'status_text' => 'Uploaded',
            ]);
    }

    #[Test]
    public function test_status_handles_unknown_status(): void
    {
        $video = Video::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('isVideoReady')->andReturn(false);
                $mock->shouldReceive('getVideo')->andReturn(['status' => 99]);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.status', $video->id));

        $response->assertOk()
            ->assertJson([
                'is_ready' => false,
                'status' => 99,
                'status_text' => 'Unknown',
            ]);
    }

    #[Test]
    public function test_status_returns_404_when_video_not_found(): void
    {
        $response = $this->getJson(route('dashboard.api.videos.status', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_store_handles_bunny_stream_upload_failure(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('uploadVideo')
                    ->once()
                    ->andReturn(null);
            })
        );

        $response = $this->postJson(route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertStatus(500)
            ->assertJson(['message' => 'Failed to upload video to Bunny Stream.']);
    }

    #[Test]
    public function test_store_handles_storage_failure(): void
    {
        $picture = Picture::factory()->create();

        Storage::shouldReceive('disk->putFile')
            ->once()
            ->andReturn(false);

        $response = $this->postJson(route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertStatus(500)
            ->assertJson(['message' => 'Failed to store video file.']);
    }

    #[Test]
    public function test_store_handles_exception(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('uploadVideo')
                    ->once()
                    ->andThrow(new Exception('Test exception'));
            })
        );

        Log::shouldReceive('error')
            ->once()
            ->with('Error uploading video', Mockery::type('array'));

        $response = $this->postJson(route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertStatus(500)
            ->assertJson(['message' => 'Error while uploading video: Test exception']);
    }

    #[Test]
    public function test_store_maps_transcoding_status(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('uploadVideo')
                    ->once()
                    ->andReturn([
                        'guid' => 'bunny-12345',
                        'status' => 3,
                    ]);
            })
        );

        $response = $this->postJson(route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertCreated();

        $video = Video::latest()->first();
        $this->assertEquals(VideoStatus::TRANSCODING, $video->status);
    }

    #[Test]
    public function test_store_maps_ready_status(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('uploadVideo')
                    ->once()
                    ->andReturn([
                        'guid' => 'bunny-12345',
                        'status' => 4,
                    ]);
            })
        );

        $response = $this->postJson(route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertCreated();

        $video = Video::latest()->first();
        $this->assertEquals(VideoStatus::READY, $video->status);
    }

    #[Test]
    public function test_store_maps_error_status(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('uploadVideo')
                    ->once()
                    ->andReturn([
                        'guid' => 'bunny-12345',
                        'status' => 5,
                    ]);
            })
        );

        $response = $this->postJson(route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertCreated();

        $video = Video::latest()->first();
        $this->assertEquals(VideoStatus::ERROR, $video->status);
    }

    #[Test]
    public function test_store_maps_default_pending_status(): void
    {
        Storage::fake('local');
        $picture = Picture::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('uploadVideo')
                    ->once()
                    ->andReturn([
                        'guid' => 'bunny-12345',
                        'status' => 1, // Any status not explicitly mapped
                    ]);
            })
        );

        $response = $this->postJson(route('dashboard.api.videos.store'), [
            'video' => UploadedFile::fake()->create('video.mp4', 1024 * 1024 * 10, 'video/mp4'),
            'name' => 'Test Video',
            'cover_picture_id' => $picture->id,
        ]);

        $response->assertCreated();

        $video = Video::latest()->first();
        $this->assertEquals(VideoStatus::PENDING, $video->status);
    }

    #[Test]
    public function test_destroy_handles_bunny_stream_deletion_failure(): void
    {
        $video = Video::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('deleteVideo')
                    ->once()
                    ->andReturn(false);
            })
        );

        Log::shouldReceive('warning')
            ->once()
            ->with('Failed to delete video from Bunny Stream', Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Video deleted', Mockery::type('array'));

        $response = $this->deleteJson(route('dashboard.api.videos.destroy', $video->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    #[Test]
    public function test_destroy_handles_exception(): void
    {
        $video = Video::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) {
                $mock->shouldReceive('deleteVideo')
                    ->once()
                    ->andThrow(new Exception('Test exception'));
            })
        );

        Log::shouldReceive('error')
            ->once()
            ->with('Error deleting video', Mockery::type('array'));

        $response = $this->deleteJson(route('dashboard.api.videos.destroy', $video->id));

        $response->assertStatus(500)
            ->assertJson(['message' => 'Error while deleting video: Test exception']);

        $this->assertDatabaseHas('videos', ['id' => $video->id]);
    }

    #[Test]
    public function test_show_returns_video_with_bunny_data(): void
    {
        $video = Video::factory()->create();
        $bunnyData = [
            'status' => 4,
            'length' => 120,
            'width' => 1920,
            'height' => 1080,
            'storageSize' => 1024000,
        ];

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) use ($bunnyData, $video) {
                $mock->shouldReceive('getVideo')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn($bunnyData);
                $mock->shouldReceive('getPlaybackUrl')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn('https://example.com/playback');
                $mock->shouldReceive('getThumbnailUrl')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn('https://example.com/thumbnail');
                $mock->shouldReceive('isVideoReady')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn(true);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.show', $video->id));

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'bunny_data' => [
                    'status',
                    'duration',
                    'width',
                    'height',
                    'size',
                    'playback_url',
                    'thumbnail_url',
                    'is_ready',
                ],
            ])
            ->assertJson([
                'bunny_data' => [
                    'status' => 4,
                    'duration' => 120,
                    'width' => 1920,
                    'height' => 1080,
                    'size' => 1024000,
                    'playback_url' => 'https://example.com/playback',
                    'thumbnail_url' => 'https://example.com/thumbnail',
                    'is_ready' => true,
                ],
            ]);
    }

    #[Test]
    public function test_show_returns_video_without_bunny_data_when_service_fails(): void
    {
        $video = Video::factory()->create();

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) use ($video) {
                $mock->shouldReceive('getVideo')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn(null);
            })
        );

        $response = $this->getJson(route('dashboard.api.videos.show', $video->id));

        $response->assertOk()
            ->assertJsonMissing(['bunny_data']);
    }

    #[Test]
    public function test_index_returns_videos_with_cover_pictures(): void
    {
        $videos = Video::factory()->count(2)->create();

        $response = $this->getJson(route('dashboard.api.videos.index'));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'cover_picture',
                ],
            ]);
    }

    #[Test]
    public function test_download_thumbnail_success(): void
    {
        Queue::fake();
        Storage::fake('public');

        $video = Video::factory()->create([
            'status' => VideoStatus::READY,
            'bunny_video_id' => 'bunny-12345',
        ]);

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) use ($video) {
                $mock->shouldReceive('getThumbnailUrl')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn('https://example.com/thumbnail.jpg');
            })
        );

        Http::fake([
            'https://example.com/thumbnail.jpg' => Http::response('fake-image-content', 200),
        ]);

        $response = $this->postJson(route('dashboard.api.videos.download-thumbnail', $video->id));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'picture' => [
                    'id',
                    'filename',
                    'path_original',
                ],
                'video' => [
                    'id',
                    'cover_picture_id',
                ],
            ]);

        // Verify video was updated with cover picture
        $video->refresh();
        $this->assertNotNull($video->cover_picture_id);

        // Verify picture was created and linked to video
        $picture = $video->coverPicture;
        $this->assertNotNull($picture);
        $this->assertStringStartsWith("bunny_thumbnail_{$video->bunny_video_id}_", $picture->filename);

        // Verify PictureJob was dispatched
        Queue::assertPushed(PictureJob::class);
    }

    #[Test]
    public function test_download_thumbnail_fails_when_video_not_ready(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
        ]);

        $response = $this->postJson(route('dashboard.api.videos.download-thumbnail', $video->id));

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Video must be ready before downloading thumbnail.',
            ]);
    }

    #[Test]
    public function test_download_thumbnail_fails_when_bunny_service_fails(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::READY,
        ]);

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) use ($video) {
                $mock->shouldReceive('getThumbnailUrl')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn(null);
            })
        );

        $response = $this->postJson(route('dashboard.api.videos.download-thumbnail', $video->id));

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Failed to get thumbnail URL from Bunny Stream.',
            ]);
    }

    #[Test]
    public function test_download_thumbnail_fails_when_http_download_fails(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::READY,
        ]);

        $this->instance(
            BunnyStreamService::class,
            Mockery::mock(BunnyStreamService::class, function (MockInterface $mock) use ($video) {
                $mock->shouldReceive('getThumbnailUrl')
                    ->once()
                    ->with($video->bunny_video_id)
                    ->andReturn('https://example.com/thumbnail.jpg');
            })
        );

        Http::fake([
            'https://example.com/thumbnail.jpg' => Http::response('', 404),
        ]);

        $response = $this->postJson(route('dashboard.api.videos.download-thumbnail', $video->id));

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Failed to download thumbnail from Bunny Stream.',
            ]);
    }
}
