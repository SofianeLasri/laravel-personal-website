<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Video\VideoRequest;
use App\Http\Requests\Video\VideoUploadRequest;
use App\Jobs\PictureJob;
use App\Models\Picture;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Visibility;
use Throwable;

class VideoController extends Controller
{
    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(Video::with('coverPicture')->get());
    }

    /**
     * Upload a video file to Bunny Stream and store it in the database.
     *
     * @throws Throwable
     */
    public function store(VideoUploadRequest $request): JsonResponse
    {
        try {
            $uploadedFile = $request->file('video');
            $name = $request->input('name', $uploadedFile->getClientOriginalName());

            $relativeFilePath = Storage::disk('local')->putFile('videos', $uploadedFile, Visibility::PRIVATE);
            if ($relativeFilePath === false) {
                return response()->json([
                    'message' => 'Failed to store video file.',
                ], 500);
            }
            $absoluteFilePath = Storage::path($relativeFilePath);

            $uploadedVideoData = $this->bunnyStreamService->uploadVideo($name, $absoluteFilePath);

            if (! $uploadedVideoData) {
                return response()->json([
                    'message' => 'Failed to upload video to Bunny Stream.',
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Error uploading video', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error while uploading video: '.$e->getMessage(),
            ], 500);
        }

        $videoStatus = match ($uploadedVideoData['status']) {
            3 => VideoStatus::TRANSCODING,
            4 => VideoStatus::READY,
            5, 6 => VideoStatus::ERROR,
            default => VideoStatus::PENDING,
        };

        $coverPictureId = $request->input('cover_picture_id');
        $video = Video::create([
            'name' => $name,
            'path' => $relativeFilePath,
            'cover_picture_id' => $coverPictureId,
            'bunny_video_id' => $uploadedVideoData['guid'],
            'status' => $videoStatus,
            'visibility' => VideoVisibility::PRIVATE,
        ]);

        return response()->json($video->load('coverPicture'), 201);
    }

    public function show(int $videoId): JsonResponse
    {
        $video = Video::with('coverPicture')->findOrFail($videoId);
        $bunnyVideoData = $this->bunnyStreamService->getVideo($video->bunny_video_id);

        $response = $video->toArray();

        if ($bunnyVideoData) {
            $response['bunny_data'] = [
                'status' => $bunnyVideoData['status'],
                'duration' => $bunnyVideoData['length'],
                'width' => $bunnyVideoData['width'],
                'height' => $bunnyVideoData['height'],
                'size' => $bunnyVideoData['storageSize'],
                'playback_url' => $this->bunnyStreamService->getPlaybackUrl($video->bunny_video_id),
                'thumbnail_url' => $this->bunnyStreamService->getThumbnailUrl($video->bunny_video_id),
                'is_ready' => $this->bunnyStreamService->isVideoReady($video->bunny_video_id),
            ];
        }

        return response()->json($response);
    }

    public function update(VideoRequest $request, int $videoId): JsonResponse
    {
        $video = Video::findOrFail($videoId);

        if ($request->input('visibility') == VideoVisibility::PUBLIC->value && $video->status !== VideoStatus::READY) {
            return response()->json([
                'error' => 'Cannot set visibility to public until video is ready.',
            ], 409);
        }

        $video->update($request->validated());

        return response()->json($video->load('coverPicture'));
    }

    public function destroy(int $videoId): JsonResponse|Response
    {
        if (! Video::where('id', $videoId)->exists()) {
            return response()->json([
                'message' => 'Video not found.',
            ], 404);
        }

        try {
            $video = Video::findOrFail($videoId);
            $deletionSuccess = $this->bunnyStreamService->deleteVideo($video->bunny_video_id);

            if (! $deletionSuccess) {
                Log::warning('Failed to delete video from Bunny Stream', [
                    'video_id' => $video->id,
                    'bunny_video_id' => $video->bunny_video_id,
                ]);
            }

            $video->delete();

            Log::info('Video deleted', [
                'video_id' => $videoId,
                'bunny_video_id' => $video->bunny_video_id,
                'bunny_deletion_success' => $deletionSuccess,
            ]);

            return response()->noContent();

        } catch (Exception $e) {
            Log::error('Error deleting video', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error while deleting video: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les métadonnées d'une vidéo depuis Bunny Stream
     */
    public function metadata(int $videoId): JsonResponse
    {
        $video = Video::findOrFail($videoId);

        $metadata = $this->bunnyStreamService->getVideoMetadata($video->bunny_video_id);

        if (! $metadata) {
            return response()->json([
                'message' => 'Video metadata not found.',
            ], 404);
        }

        return response()->json($metadata);
    }

    /**
     * Vérifier le statut de traitement d'une vidéo
     */
    public function status(int $videoId): JsonResponse
    {
        $video = Video::findOrFail($videoId);

        $isReady = $this->bunnyStreamService->isVideoReady($video->bunny_video_id);
        $bunnyData = $this->bunnyStreamService->getVideo($video->bunny_video_id);

        return response()->json([
            'is_ready' => $isReady,
            'status' => $bunnyData['status'] ?? null,
            'status_text' => $this->getStatusText($bunnyData['status'] ?? null),
        ]);
    }

    /**
     * Download the video thumbnail from Bunny Stream and store it as a Picture
     */
    public function downloadThumbnail(int $videoId): JsonResponse
    {
        $video = Video::findOrFail($videoId);

        // Check if video is ready
        if ($video->status !== VideoStatus::READY) {
            return response()->json([
                'message' => 'Video must be ready before downloading thumbnail.',
            ], 400);
        }

        try {
            // Get thumbnail URL from Bunny Stream
            $thumbnailUrl = $this->bunnyStreamService->getThumbnailUrl($video->bunny_video_id);

            if (! $thumbnailUrl) {
                return response()->json([
                    'message' => 'Failed to get thumbnail URL from Bunny Stream.',
                ], 500);
            }

            // Download the thumbnail image
            $response = Http::get($thumbnailUrl);

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'Failed to download thumbnail from Bunny Stream.',
                ], 500);
            }

            // Store the image file
            $folderName = 'uploads/'.Carbon::now()->format('Y/m/d');
            $fileName = 'bunny_thumbnail_'.$video->bunny_video_id.'_'.uniqid().'.jpg';
            $filePath = $folderName.'/'.$fileName;

            // Save to local storage
            Storage::disk('public')->put($filePath, $response->body());

            // Save to CDN if configured
            if (config('app.cdn_disk')) {
                Storage::disk(config('app.cdn_disk'))->put($filePath, $response->body());
            }

            // Create Picture record
            $picture = Picture::create([
                'filename' => $fileName,
                'size' => strlen($response->body()),
                'path_original' => $filePath,
            ]);

            // Dispatch optimization job
            PictureJob::dispatch($picture);

            // Update video to use this as cover picture
            $video->update(['cover_picture_id' => $picture->id]);

            return response()->json([
                'message' => 'Thumbnail downloaded and set as cover picture successfully.',
                'picture' => $picture->load('optimizedPictures'),
                'video' => $video->load('coverPicture'),
            ]);

        } catch (Exception $e) {
            Log::error('Error downloading video thumbnail', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error while downloading thumbnail: '.$e->getMessage(),
            ], 500);
        }
    }

    private function getStatusText(?int $status): string
    {
        return match ($status) {
            0 => 'Created',
            1 => 'Uploaded',
            2 => 'Processing',
            3 => 'Transcoding',
            4 => 'Finished',
            5 => 'Error',
            default => 'Unknown',
        };
    }
}
