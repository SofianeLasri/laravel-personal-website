<?php

namespace App\Console\Commands;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CheckPendingVideosStatusCommand extends Command
{
    protected $signature = 'check:pending-videos-status';

    protected $description = 'Check the pending and transcoding videos from the Bunny Stream api.';

    public function handle(BunnyStreamService $bunnyStreamService): void
    {
        $pendingVideos = Video::whereIn('status', [VideoStatus::PENDING->value, VideoStatus::TRANSCODING->value])->get();

        foreach ($pendingVideos as $video) {
            $this->info("Checking video: {$video->name}");

            try {
                $videoData = $bunnyStreamService->getVideo($video->bunny_video_id);

                if (empty($videoData['status'])) {
                    throw new Exception("Bunny Stream response doesn't have 'status' object.");
                }

                $videoStatus = match ($videoData['status']) {
                    3 => VideoStatus::TRANSCODING,
                    4 => VideoStatus::READY,
                    5, 6 => VideoStatus::ERROR,
                    default => VideoStatus::PENDING,
                };

                if ($videoData['status'] == 4) {
                    $video->visibility = VideoVisibility::PUBLIC;

                    $localVideoDeleted = Storage::disk('local')->delete($video->path);

                    if ($localVideoDeleted) {
                        $video->path = '';
                    } else {
                        Log::warning("Failed to delete local video file for {$video->id}.");
                    }
                }

                $video->status = $videoStatus;
                $video->save();
            } catch (Exception $exception) {
                Log::error('Unable to get video data from Bunny Stream API. Video id: '.$video->bunny_video_id);
                Log::error($exception->getMessage(), $exception->getTrace());
            }
        }
    }
}
