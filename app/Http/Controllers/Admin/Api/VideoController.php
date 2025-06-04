<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Video\VideoRequest;
use App\Http\Requests\Video\VideoUploadRequest;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Visibility;
use Throwable;

class VideoController extends Controller
{
    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService
    ) {}

    public function index()
    {
        return Video::with('coverPicture')->get();
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

        $coverPictureId = $request->input('cover_picture_id');
        $video = Video::create([
            'name' => $name,
            'path' => $relativeFilePath,
            'cover_picture_id' => $coverPictureId,
            'bunny_video_id' => $uploadedVideoData['guid'],
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
                'status' => $bunnyVideoData['status'] ?? null,
                'duration' => $bunnyVideoData['length'] ?? null,
                'width' => $bunnyVideoData['width'] ?? null,
                'height' => $bunnyVideoData['height'] ?? null,
                'size' => $bunnyVideoData['storageSize'] ?? null,
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

    public function destroy(int $videoId)
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
