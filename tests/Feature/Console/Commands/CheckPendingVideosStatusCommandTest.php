<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\CheckPendingVideosStatusCommand;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CheckPendingVideosStatusCommand::class)]
class CheckPendingVideosStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    private BunnyStreamService $bunnyStreamService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bunnyStreamService = Mockery::mock(BunnyStreamService::class);
        $this->app->instance(BunnyStreamService::class, $this->bunnyStreamService);

        Storage::fake('local');
    }

    #[Test]
    public function command_has_correct_signature(): void
    {
        $command = new CheckPendingVideosStatusCommand;

        $this->assertEquals('check:pending-videos-status', $command->getName());
    }

    #[Test]
    public function command_has_correct_description(): void
    {
        $command = new CheckPendingVideosStatusCommand;

        $this->assertEquals(
            'Check the pending and transcoding videos from the Bunny Stream api.',
            $command->getDescription()
        );
    }

    #[Test]
    public function command_processes_no_videos_when_none_are_pending(): void
    {
        Video::factory()->create(['status' => VideoStatus::READY]);
        Video::factory()->create(['status' => VideoStatus::ERROR]);

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();
    }

    #[Test]
    public function command_processes_pending_videos(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
            'name' => 'Test Video',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 3]);

        $this->artisan('check:pending-videos-status')
            ->expectsOutput('Checking video: Test Video')
            ->assertSuccessful();

        $this->assertEquals(VideoStatus::TRANSCODING, $video->fresh()->status);
    }

    #[Test]
    public function command_processes_transcoding_videos(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::TRANSCODING,
            'bunny_video_id' => 'test-video-id',
            'name' => 'Test Video',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 4]);

        Storage::shouldReceive('disk')
            ->with('local')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->artisan('check:pending-videos-status')
            ->expectsOutput('Checking video: Test Video')
            ->assertSuccessful();

        $video->refresh();
        $this->assertEquals(VideoStatus::READY, $video->status);
        $this->assertEquals(VideoVisibility::PUBLIC, $video->visibility);
        $this->assertEquals('', $video->path);
    }

    #[Test]
    public function command_updates_video_status_to_transcoding(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 3]);

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        $this->assertEquals(VideoStatus::TRANSCODING, $video->fresh()->status);
    }

    #[Test]
    public function command_updates_video_status_to_ready(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
            'path' => 'videos/test.mp4',
            'visibility' => VideoVisibility::PRIVATE,
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 4]);

        Storage::shouldReceive('disk')
            ->with('local')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('delete')
            ->with('videos/test.mp4')
            ->once()
            ->andReturn(true);

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        $video->refresh();
        $this->assertEquals(VideoStatus::READY, $video->status);
        $this->assertEquals(VideoVisibility::PUBLIC, $video->visibility);
        $this->assertEquals('', $video->path);
    }

    #[Test]
    public function command_updates_video_status_to_error_for_status_5(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 5]);

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        $this->assertEquals(VideoStatus::ERROR, $video->fresh()->status);
    }

    #[Test]
    public function command_updates_video_status_to_error_for_status_6(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 6]);

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        $this->assertEquals(VideoStatus::ERROR, $video->fresh()->status);
    }

    #[Test]
    public function command_keeps_pending_status_for_unknown_status(): void
    {
        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 99]); // Unknown status

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        $this->assertEquals(VideoStatus::PENDING, $video->fresh()->status);
    }

    #[Test]
    public function command_logs_warning_when_local_file_deletion_fails(): void
    {
        Log::spy();

        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
            'path' => 'videos/test.mp4',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn(['status' => 4]);

        Storage::shouldReceive('disk')
            ->with('local')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('delete')
            ->with('videos/test.mp4')
            ->once()
            ->andReturn(false);

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        $video->refresh();
        $this->assertEquals(VideoStatus::READY, $video->status);
        $this->assertEquals('videos/test.mp4', $video->path); // Path should remain unchanged

        Log::shouldHaveReceived('warning')
            ->with("Failed to delete local video file for {$video->id}.")
            ->once();
    }

    #[Test]
    public function command_handles_bunny_stream_api_exception(): void
    {
        Log::spy();

        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
        ]);

        $exception = new Exception('API Error');

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andThrow($exception);

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        // Video status should remain unchanged
        $this->assertEquals(VideoStatus::PENDING, $video->fresh()->status);

        Log::shouldHaveReceived('error')
            ->with('Unable to get video data from Bunny Stream API. Video id: test-video-id')
            ->once();

        Log::shouldHaveReceived('error')
            ->with('API Error', $exception->getTrace())
            ->once();
    }

    #[Test]
    public function command_handles_missing_status_in_response(): void
    {
        Log::spy();

        $video = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'test-video-id',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('test-video-id')
            ->once()
            ->andReturn([]); // Empty response

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();

        // Video status should remain unchanged
        $this->assertEquals(VideoStatus::PENDING, $video->fresh()->status);

        Log::shouldHaveReceived('error')
            ->with('Unable to get video data from Bunny Stream API. Video id: test-video-id')
            ->once();

        Log::shouldHaveReceived('error')
            ->with("Bunny Stream response doesn't have 'status' object.", Mockery::any())
            ->once();
    }

    #[Test]
    public function command_processes_multiple_videos(): void
    {
        $video1 = Video::factory()->create([
            'status' => VideoStatus::PENDING,
            'bunny_video_id' => 'video-1',
            'name' => 'Video 1',
        ]);

        $video2 = Video::factory()->create([
            'status' => VideoStatus::TRANSCODING,
            'bunny_video_id' => 'video-2',
            'name' => 'Video 2',
        ]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('video-1')
            ->once()
            ->andReturn(['status' => 3]);

        $this->bunnyStreamService
            ->shouldReceive('getVideo')
            ->with('video-2')
            ->once()
            ->andReturn(['status' => 4]);

        Storage::shouldReceive('disk')
            ->with('local')
            ->once()
            ->andReturnSelf();

        Storage::shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->artisan('check:pending-videos-status')
            ->expectsOutput('Checking video: Video 1')
            ->expectsOutput('Checking video: Video 2')
            ->assertSuccessful();

        $this->assertEquals(VideoStatus::TRANSCODING, $video1->fresh()->status);
        $this->assertEquals(VideoStatus::READY, $video2->fresh()->status);
        $this->assertEquals(VideoVisibility::PUBLIC, $video2->fresh()->visibility);
    }

    #[Test]
    public function command_ignores_videos_with_ready_status(): void
    {
        Video::factory()->create([
            'status' => VideoStatus::READY,
            'bunny_video_id' => 'ready-video',
        ]);

        $this->bunnyStreamService
            ->shouldNotReceive('getVideo');

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();
    }

    #[Test]
    public function command_ignores_videos_with_error_status(): void
    {
        Video::factory()->create([
            'status' => VideoStatus::ERROR,
            'bunny_video_id' => 'error-video',
        ]);

        $this->bunnyStreamService
            ->shouldNotReceive('getVideo');

        $this->artisan('check:pending-videos-status')
            ->assertSuccessful();
    }
}
