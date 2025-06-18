<?php

namespace Tests\Feature\Services;

use App\Services\BunnyStreamService;
use Corbpie\BunnyCdn\BunnyAPIStream;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BunnyStreamService::class)]
class BunnyStreamServiceTest extends TestCase
{
    use RefreshDatabase;

    protected array $createVideoData = [
        'videoLibraryId' => 12345,
        'guid' => 'test-video-id',
        'title' => 'test-video.mp4',
        'description' => '',
        'dateUploaded' => '2023-12-01T12:00:00Z',
        'views' => 0,
        'isPublic' => true,
        'length' => 60,
        'status' => 2, // Processing
        'framerate' => 30.0,
        'rotation' => 0,
        'width' => 1920,
        'height' => 1080,
        'availableResolutions' => [360, 480, 720, 1080],
        'outputCodecs' => 'vp9',
        'thumbnailCount' => 1,
        'encodeProgress' => 50,
        'storageSize' => 500000,
        'captions' => [],
        'hasMP4Fallback' => true,
        'collectionId' => null,
        'thumbnailFileName' => 'thumbnail.jpg',
        'averageWatchTime' => 0,
        'totalWatchTime' => 0,
        'category' => null,
        'chapters' => [],
        'moments' => [],
        'metaTags' => [],
        'transcodingMessages' => [],
        'message' => null,
        'jitEncodingEnabled' => null,
    ];

    protected array $uploadVideoDataSuccess = [
        'success' => true,
        'message' => 'Video uploaded successfully',
        'statusCode' => 200,
    ];

    protected array $uploadVideoDataFailure = [
        'success' => false,
        'message' => 'Upload failed',
        'statusCode' => 500,
    ];

    protected array $getVideoData = [
        'videoLibraryId' => 12345,
        'guid' => 'test-video-id',
        'title' => 'test-video.mp4',
        'description' => '',
        'dateUploaded' => '2023-12-01T12:00:00Z',
        'views' => 350,
        'isPublic' => true,
        'length' => 60,
        'status' => 4, // Finished
        'framerate' => 30.0,
        'rotation' => 0,
        'width' => 1920,
        'height' => 1080,
        'availableResolutions' => [360, 480, 720, 1080],
        'outputCodecs' => 'vp9',
        'thumbnailCount' => 1,
        'encodeProgress' => 100,
        'storageSize' => 500000,
        'captions' => [],
        'hasMP4Fallback' => true,
        'collectionId' => null,
        'thumbnailFileName' => 'thumbnail.jpg',
        'averageWatchTime' => 120,
        'totalWatchTime' => 3000,
        'category' => null,
        'chapters' => [],
        'moments' => [],
        'metaTags' => [],
        'transcodingMessages' => [],
        'message' => null,
        'jitEncodingEnabled' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Mock des configurations
        config([
            'services.bunny.stream_api_key' => 'test-api-key',
            'services.bunny.stream_library_id' => 12345,
            'services.bunny.stream_pull_zone' => 'test-pull-zone',
        ]);
    }

    #[Test]
    public function test_upload_video_success()
    {
        Storage::fake('local');

        $createVideoData = $this->createVideoData;
        $uploadVideoData = $this->uploadVideoDataSuccess;

        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) use ($uploadVideoData, $createVideoData) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('createVideo')
                ->with('test-video.mp4')
                ->andReturn($createVideoData)
                ->once();
            $mock->shouldReceive('uploadVideo')
                ->with('test-video-id', Mockery::any())
                ->andReturn($uploadVideoData)
                ->once();
        });

        $uploadedFile = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');
        Storage::fake('local')->putFileAs('videos', $uploadedFile, 'test-video.mp4');

        $service = app(BunnyStreamService::class);
        $result = $service->uploadVideo('test-video.mp4', 'videos/test-video.mp4');

        $this->assertNotNull($result);
        $this->assertEquals('test-video-id', $result['guid']);
        $this->assertEquals('test-video.mp4', $result['title']);
    }

    #[Test]
    public function test_upload_video_create_video_fails()
    {
        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('createVideo')
                ->with('test-video.mp4')
                ->andReturn([])
                ->once();
        });

        $uploadedFile = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');
        Storage::fake()->putFileAs('videos', $uploadedFile, 'test-video.mp4');

        $service = app(BunnyStreamService::class);
        $result = $service->uploadVideo('test-video.mp4', 'videos/test-video.mp4');

        $this->assertNull($result);
    }

    #[Test]
    public function test_upload_video_upload_file_fails()
    {
        $createVideoData = $this->createVideoData;
        $uploadVideoData = $this->uploadVideoDataFailure;

        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) use ($uploadVideoData, $createVideoData) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('createVideo')
                ->with('test-video.mp4')
                ->andReturn($createVideoData)
                ->once();
            $mock->shouldReceive('uploadVideo')
                ->with('test-video-id', Mockery::any())
                ->andReturn($uploadVideoData)
                ->once();
            $mock->shouldReceive('deleteVideo')
                ->with('test-video-id')
                ->andReturn([
                    'success' => true,
                    'message' => 'Video deleted successfully',
                    'statusCode' => 200,
                ])
                ->once();
        });

        $uploadedFile = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');
        Storage::fake()->putFileAs('videos', $uploadedFile, 'test-video.mp4');

        $service = app(BunnyStreamService::class);
        $result = $service->uploadVideo('test-video.mp4', 'videos/test-video.mp4');

        $this->assertNull($result);
    }

    #[Test]
    public function test_upload_video_with_exception()
    {
        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('createVideo')
                ->with('test-video.mp4')
                ->andThrow(new Exception('Test exception'))
                ->once();
        });

        $uploadedFile = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');
        Storage::fake()->putFileAs('videos', $uploadedFile, 'test-video.mp4');

        $service = app(BunnyStreamService::class);
        $result = $service->uploadVideo('test-video.mp4', 'videos/test-video.mp4');

        $this->assertNull($result);
    }

    #[Test]
    public function test_get_video_success()
    {
        $videoData = $this->getVideoData;

        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) use ($videoData) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('getVideo')
                ->with('test-video-id')
                ->andReturn($videoData)
                ->once();
        });

        $service = app(BunnyStreamService::class);
        $result = $service->getVideo('test-video-id');

        $this->assertNotNull($result);
        $this->assertEquals('test-video-id', $result['guid']);
        $this->assertEquals('test-video.mp4', $result['title']);
        $this->assertEquals(4, $result['status']);
    }

    #[Test]
    public function test_delete_video_success()
    {
        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('deleteVideo')
                ->with('test-video-id')
                ->andReturn([
                    'success' => true,
                    'message' => 'Video deleted successfully',
                    'statusCode' => 200,
                ])
                ->once();
        });

        $service = app(BunnyStreamService::class);
        $result = $service->deleteVideo('test-video-id');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_get_playback_url()
    {
        $service = app(BunnyStreamService::class);
        $url = $service->getPlaybackUrl('test-video-id');

        $this->assertEquals('https://iframe.mediadelivery.net/embed/12345/test-video-id', $url);
    }

    #[Test]
    public function test_get_thumbnail_url()
    {
        $videoData = $this->getVideoData;

        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) use ($videoData) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('getVideo')
                ->with('test-video-id')
                ->andReturn($videoData)
                ->once();
        });

        $service = app(BunnyStreamService::class);
        $url = $service->getThumbnailUrl('test-video-id', 1280, 720);

        $this->assertEquals('https://test-pull-zone.b-cdn.net/test-video-id/thumbnail.jpg?width=1280&height=720', $url);
    }

    #[Test]
    public function test_is_video_ready_true()
    {
        $videoData = $this->getVideoData;

        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) use ($videoData) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('getVideo')
                ->with('test-video-id')
                ->andReturn($videoData)
                ->once();
        });

        $service = app(BunnyStreamService::class);
        $result = $service->isVideoReady('test-video-id');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_is_video_ready_false()
    {
        $videoData = $this->createVideoData;

        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) use ($videoData) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('getVideo')
                ->with('test-video-id')
                ->andReturn($videoData)
                ->once();
        });

        $service = app(BunnyStreamService::class);
        $result = $service->isVideoReady('test-video-id');

        $this->assertFalse($result);
    }

    #[Test]
    public function test_get_video_metadata()
    {
        $videoData = $this->getVideoData;

        $this->mock(BunnyAPIStream::class, function (MockInterface $mock) use ($videoData) {
            $mock->shouldReceive('apiKey')->with('test-api-key')->once();
            $mock->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
            $mock->shouldReceive('setStreamLibraryId')->with(12345)->once();
            $mock->shouldReceive('getVideo')
                ->with('test-video-id')
                ->andReturn($videoData)
                ->once();
        });

        $service = app(BunnyStreamService::class);
        $metadata = $service->getVideoMetadata('test-video-id');

        $this->assertNotNull($metadata);
        $this->assertEquals(60, $metadata['duration']);
        $this->assertEquals(1920, $metadata['width']);
        $this->assertEquals(1080, $metadata['height']);
        $this->assertEquals(500000, $metadata['size']);
        $this->assertEquals(4, $metadata['status']);
        $this->assertEquals('2023-12-01T12:00:00Z', $metadata['created_at']);
    }
}
