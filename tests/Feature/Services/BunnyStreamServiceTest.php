<?php

namespace Tests\Feature\Services;

use App\Services\BunnyStreamService;
use Corbpie\BunnyCdn\BunnyAPIStream;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BunnyStreamService::class)]
class BunnyStreamServiceTest extends TestCase
{
    use RefreshDatabase;

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
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'title' => 'test-video.mp4',
        ];

        $mockBunnyStream->shouldReceive('createVideo')
            ->with('test-video.mp4')
            ->andReturn($videoData)
            ->once();

        $mockBunnyStream->shouldReceive('uploadVideo')
            ->with('test-video-id', Mockery::any())
            ->andReturn(true)
            ->once();

        $file = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->uploadVideo($file);

        $this->assertNotNull($result);
        $this->assertEquals('test-video-id', $result['guid']);
        $this->assertEquals('test-video.mp4', $result['title']);
    }

    #[Test]
    public function test_upload_video_create_video_fails()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $mockBunnyStream->shouldReceive('createVideo')
            ->with('test-video.mp4')
            ->andReturn(null)
            ->once();

        Log::shouldReceive('error')
            ->with('BunnyStreamService: Failed to create video entry')
            ->once();

        $file = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->uploadVideo($file);

        $this->assertNull($result);
    }

    #[Test]
    public function test_upload_video_upload_file_fails()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'title' => 'test-video.mp4',
        ];

        $mockBunnyStream->shouldReceive('createVideo')
            ->with('test-video.mp4')
            ->andReturn($videoData)
            ->once();

        $mockBunnyStream->shouldReceive('uploadVideo')
            ->with('test-video-id', Mockery::any())
            ->andReturn(false)
            ->once();

        $mockBunnyStream->shouldReceive('deleteVideo')
            ->with('test-video-id')
            ->andReturn(true)
            ->once();

        Log::shouldReceive('error')
            ->with('BunnyStreamService: Failed to upload video file', ['video_id' => 'test-video-id'])
            ->once();

        $file = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->uploadVideo($file);

        $this->assertNull($result);
    }

    #[Test]
    public function test_upload_video_with_exception()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $mockBunnyStream->shouldReceive('createVideo')
            ->with('test-video.mp4')
            ->andThrow(new Exception('Test exception'))
            ->once();

        Log::shouldReceive('error')
            ->with('BunnyStreamService: Error uploading video', [
                'error' => 'Test exception',
                'file' => 'test-video.mp4',
            ])
            ->once();

        $file = UploadedFile::fake()->create('test-video.mp4', 1000, 'video/mp4');

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->uploadVideo($file);

        $this->assertNull($result);
    }

    #[Test]
    public function test_get_video_success()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'title' => 'test-video.mp4',
            'status' => 4,
        ];

        $mockBunnyStream->shouldReceive('getVideo')
            ->with('test-video-id')
            ->andReturn($videoData)
            ->once();

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->getVideo('test-video-id');

        $this->assertNotNull($result);
        $this->assertEquals('test-video-id', $result['guid']);
        $this->assertEquals('test-video.mp4', $result['title']);
        $this->assertEquals(4, $result['status']);
    }

    #[Test]
    public function test_delete_video_success()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $mockBunnyStream->shouldReceive('deleteVideo')
            ->with('test-video-id')
            ->andReturn(true)
            ->once();

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->deleteVideo('test-video-id');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_get_playback_url()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'title' => 'test-video.mp4',
        ];

        $mockBunnyStream->shouldReceive('getVideo')
            ->with('test-video-id')
            ->andReturn($videoData)
            ->once();

        $service = new BunnyStreamService($mockBunnyStream);
        $url = $service->getPlaybackUrl('test-video-id');

        $this->assertEquals('https://iframe.mediadelivery.net/embed/12345/test-video-id', $url);
    }

    #[Test]
    public function test_get_thumbnail_url()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'title' => 'test-video.mp4',
        ];

        $mockBunnyStream->shouldReceive('getVideo')
            ->with('test-video-id')
            ->andReturn($videoData)
            ->once();

        $service = new BunnyStreamService($mockBunnyStream);
        $url = $service->getThumbnailUrl('test-video-id', 1280, 720);

        $this->assertEquals('https://vz-12345.b-cdn.net/test-video-id/thumbnail.jpg?width=1280&height=720', $url);
    }

    #[Test]
    public function test_is_video_ready_true()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'status' => 4, // Finished
        ];

        $mockBunnyStream->shouldReceive('getVideo')
            ->with('test-video-id')
            ->andReturn($videoData)
            ->once();

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->isVideoReady('test-video-id');

        $this->assertTrue($result);
    }

    #[Test]
    public function test_is_video_ready_false()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'status' => 2, // Processing
        ];

        $mockBunnyStream->shouldReceive('getVideo')
            ->with('test-video-id')
            ->andReturn($videoData)
            ->once();

        $service = new BunnyStreamService($mockBunnyStream);
        $result = $service->isVideoReady('test-video-id');

        $this->assertFalse($result);
    }

    #[Test]
    public function test_get_video_metadata()
    {
        $mockBunnyStream = Mockery::mock(BunnyAPIStream::class);
        $mockBunnyStream->shouldReceive('apiKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('streamLibraryAccessKey')->with('test-api-key')->once();
        $mockBunnyStream->shouldReceive('setStreamLibraryId')->with(12345)->once();

        $videoData = [
            'guid' => 'test-video-id',
            'length' => 120,
            'width' => 1920,
            'height' => 1080,
            'storageSize' => 1024000,
            'status' => 4,
            'dateUploaded' => '2023-12-01T12:00:00Z',
        ];

        $mockBunnyStream->shouldReceive('getVideo')
            ->with('test-video-id')
            ->andReturn($videoData)
            ->once();

        $service = new BunnyStreamService($mockBunnyStream);
        $metadata = $service->getVideoMetadata('test-video-id');

        $this->assertNotNull($metadata);
        $this->assertEquals(120, $metadata['duration']);
        $this->assertEquals(1920, $metadata['width']);
        $this->assertEquals(1080, $metadata['height']);
        $this->assertEquals(1024000, $metadata['size']);
        $this->assertEquals(4, $metadata['status']);
        $this->assertEquals('2023-12-01T12:00:00Z', $metadata['created_at']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
