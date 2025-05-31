<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VideoRequest;
use App\Http\Requests\VideoUploadRequest;
use App\Models\Video;
use App\Services\BunnyStreamService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class VideoController extends Controller
{
    public function __construct(
        private BunnyStreamService $bunnyStreamService
    ) {}

    public function index()
    {
        return Video::with('coverPicture')->get();
    }

    public function store(VideoRequest $request)
    {
        return Video::create($request->validated());
    }

    /**
     * Upload une vidéo vers Bunny Stream et créer l'entrée en base
     */
    public function upload(VideoUploadRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $uploadedFile = $request->file('video');
            $title = $request->input('title', $uploadedFile->getClientOriginalName());
            $coverPictureId = $request->input('cover_picture_id');

            // Upload vers Bunny Stream
            $videoData = $this->bunnyStreamService->uploadVideo($uploadedFile, $title);

            if (! $videoData) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Erreur lors de l\'upload de la vidéo vers Bunny Stream.',
                ], 500);
            }

            // Créer l'entrée en base de données
            $video = Video::create([
                'filename' => $title,
                'cover_picture_id' => $coverPictureId,
                'bunny_video_id' => $videoData['guid'],
            ]);

            DB::commit();

            Log::info('Video uploaded successfully', [
                'video_id' => $video->id,
                'bunny_video_id' => $videoData['guid'],
                'filename' => $title,
            ]);

            return response()->json($video->load('coverPicture'), 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error uploading video', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'upload de la vidéo: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(int $videoId)
    {
        $video = Video::with('coverPicture')->findOrFail($videoId);

        // Enrichir avec les données de Bunny Stream
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

    public function update(VideoRequest $request, int $videoId)
    {
        $video = Video::findOrFail($videoId);

        $video->update($request->validated());

        return $video->load('coverPicture');
    }

    public function destroy(int $videoId)
    {
        if (! Video::where('id', $videoId)->exists()) {
            return response()->json([
                'message' => 'Vidéo non trouvée.',
            ], 404);
        }

        try {
            $video = Video::findOrFail($videoId);

            DB::beginTransaction();

            // Supprimer de Bunny Stream
            $deletionSuccess = $this->bunnyStreamService->deleteVideo($video->bunny_video_id);

            if (! $deletionSuccess) {
                Log::warning('Failed to delete video from Bunny Stream', [
                    'video_id' => $video->id,
                    'bunny_video_id' => $video->bunny_video_id,
                ]);
            }

            // Supprimer de la base de données
            $video->delete();

            DB::commit();

            Log::info('Video deleted', [
                'video_id' => $videoId,
                'bunny_video_id' => $video->bunny_video_id,
                'bunny_deletion_success' => $deletionSuccess,
            ]);

            return response()->noContent();

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error deleting video', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de la suppression de la vidéo: '.$e->getMessage(),
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
                'message' => 'Impossible de récupérer les métadonnées de la vidéo.',
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
