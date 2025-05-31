<?php

namespace Tests\Feature\Models\Video;

use App\Http\Controllers\Admin\Api\VideoController;
use App\Models\Picture;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
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
    public function test_index_with_videos()
    {
        $videos = Video::factory()->count(3)->create();

        $response = $this->getJson(route('dashboard.api.videos.index'));

        $response->assertOk()
            ->assertJson($videos->toArray());
    }

    #[Test]
    public function test_index_without_videos()
    {
        $response = $this->getJson(route('dashboard.api.videos.index'));

        $response->assertOk()
            ->assertJson([]);
    }

    #[Test]
    public function test_store_video_with_valid_data()
    {
        $picture = Picture::factory()->create();

        $videoData = [
            'name' => 'Test Video',
            'path' => 'videos/test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
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

        $this->assertDatabaseHas('videos', $videoData);
    }

    #[Test]
    public function test_store_video_validation_fails_with_missing_name_path()
    {
        $picture = Picture::factory()->create();

        $videoData = [
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $response = $this->postJson(route('dashboard.api.videos.store'), $videoData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'path']);
    }

    #[Test]
    public function test_store_video_validation_fails_with_invalid_cover_picture_id()
    {
        $videoData = [
            'filename' => 'test-video.mp4',
            'cover_picture_id' => 99999,
            'bunny_video_id' => 'bunny-12345',
        ];

        $response = $this->postJson(route('dashboard.api.videos.store'), $videoData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cover_picture_id');
    }

    #[Test]
    public function test_store_video_validation_fails_with_missing_bunny_video_id()
    {
        $picture = Picture::factory()->create();

        $videoData = [
            'filename' => 'test-video.mp4',
            'cover_picture_id' => $picture->id,
        ];

        $response = $this->postJson(route('dashboard.api.videos.store'), $videoData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('bunny_video_id');
    }

    #[Test]
    public function test_show_video()
    {
        $video = Video::factory()->create();

        $response = $this->getJson(route('dashboard.api.videos.show', $video->id));

        $response->assertOk()
            ->assertJson($video->toArray());
    }

    #[Test]
    public function test_show_video_not_found()
    {
        $response = $this->getJson(route('dashboard.api.videos.show', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_update_video_with_valid_data()
    {
        $video = Video::factory()->create();
        $newPicture = Picture::factory()->create();

        $updateData = [
            'name' => 'Updated Video',
            'path' => 'videos/updated-video.mp4',
            'cover_picture_id' => $newPicture->id,
            'bunny_video_id' => 'updated-bunny-12345',
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
    public function test_update_video_validation_fails()
    {
        $video = Video::factory()->create();

        $updateData = [
            'name' => '',
            'path' => '',
            'cover_picture_id' => 99999,
            'bunny_video_id' => '',
        ];

        $response = $this->putJson(route('dashboard.api.videos.update', $video->id), $updateData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'path', 'cover_picture_id', 'bunny_video_id']);
    }

    #[Test]
    public function test_update_video_not_found()
    {
        $picture = Picture::factory()->create();

        $updateData = [
            'name' => 'Test Video',
            'path' => 'videos/test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $response = $this->putJson(route('dashboard.api.videos.update', 99999), $updateData);

        $response->assertNotFound();
    }

    #[Test]
    public function test_destroy_video()
    {
        $video = Video::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.videos.destroy', $video->id));

        $response->assertNoContent();

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    #[Test]
    public function test_destroy_video_not_found()
    {
        $response = $this->deleteJson(route('dashboard.api.videos.destroy', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_store_returns_created_video_with_relationships()
    {
        $picture = Picture::factory()->create();

        $videoData = [
            'name' => 'Test Video',
            'path' => 'videos/test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $response = $this->postJson(route('dashboard.api.videos.store'), $videoData);

        $response->assertCreated();

        $responseData = $response->json();
        $this->assertEquals($videoData['name'], $responseData['name']);
        $this->assertEquals($videoData['path'], $responseData['path']);
        $this->assertEquals($videoData['cover_picture_id'], $responseData['cover_picture_id']);
        $this->assertEquals($videoData['bunny_video_id'], $responseData['bunny_video_id']);
    }

    #[Test]
    public function test_update_returns_updated_video()
    {
        $video = Video::factory()->create();
        $newPicture = Picture::factory()->create();

        $updateData = [
            'name' => 'Updated Video',
            'path' => 'videos/updated-video.mp4',
            'cover_picture_id' => $newPicture->id,
            'bunny_video_id' => 'updated-bunny-12345',
        ];

        $response = $this->putJson(route('dashboard.api.videos.update', $video->id), $updateData);

        $response->assertOk();

        $responseData = $response->json();
        $this->assertEquals($updateData['name'], $responseData['name']);
        $this->assertEquals($updateData['path'], $responseData['path']);
        $this->assertEquals($updateData['cover_picture_id'], $responseData['cover_picture_id']);
        $this->assertEquals($updateData['bunny_video_id'], $responseData['bunny_video_id']);
    }
}
