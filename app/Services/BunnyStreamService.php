<?php

namespace App\Services;

use Corbpie\BunnyCdn\BunnyAPIStream;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class BunnyStreamService
{
    private BunnyAPIStream $bunnyStream;

    public function __construct(?BunnyAPIStream $bunnyStream = null)
    {
        $this->bunnyStream = $bunnyStream ?? new BunnyAPIStream;

        // Configuration des clés API depuis l'environnement
        $this->bunnyStream->apiKey(config('services.bunny.stream_api_key'));
        $this->bunnyStream->streamLibraryAccessKey(config('services.bunny.stream_api_key'));
        $this->bunnyStream->setStreamLibraryId((int) config('services.bunny.stream_library_id'));
    }

    /**
     * Upload une vidéo vers Bunny Stream
     *
     * @return array|null Retourne les données de la vidéo ou null en cas d'erreur
     */
    public function uploadVideo(UploadedFile $file, ?string $title = null): ?array
    {
        try {
            // Créer une vidéo dans la bibliothèque
            $videoData = $this->createVideo($title ?? $file->getClientOriginalName());

            if (! $videoData) {
                Log::error('BunnyStreamService: Failed to create video entry');

                return null;
            }

            $videoId = $videoData['guid'];

            // Upload du fichier vidéo
            $uploadResult = $this->uploadVideoFile($videoId, $file);

            if (! $uploadResult) {
                Log::error('BunnyStreamService: Failed to upload video file', ['video_id' => $videoId]);
                // Nettoyer la vidéo créée en cas d'échec d'upload
                $this->deleteVideo($videoId);

                return null;
            }

            Log::info('BunnyStreamService: Video uploaded successfully', ['video_id' => $videoId]);

            return $videoData;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error uploading video', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return null;
        }
    }

    /**
     * Créer une entrée vidéo dans Bunny Stream
     */
    public function createVideo(string $title): ?array
    {
        try {
            $response = $this->bunnyStream->createVideo($title);

            if ($response && isset($response['guid'])) {
                return $response;
            }

            Log::error('BunnyStreamService: Invalid response when creating video', ['response' => $response]);

            return null;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error creating video', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);

            return null;
        }
    }

    /**
     * Upload le fichier vidéo vers une vidéo existante
     */
    public function uploadVideoFile(string $videoId, UploadedFile $file): bool
    {
        try {
            $response = $this->bunnyStream->uploadVideo($videoId, $file->getContent());

            // La méthode uploadVideo retourne true en cas de succès
            return $response === true;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error uploading video file', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
                'file' => $file->getClientOriginalName(),
            ]);

            return false;
        }
    }

    /**
     * Récupérer les informations d'une vidéo
     */
    public function getVideo(string $videoId): ?array
    {
        try {
            $response = $this->bunnyStream->getVideo($videoId);

            if ($response && is_array($response)) {
                return $response;
            }

            return null;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error getting video', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return null;
        }
    }

    /**
     * Supprimer une vidéo de Bunny Stream
     */
    public function deleteVideo(string $videoId): bool
    {
        try {
            $response = $this->bunnyStream->deleteVideo($videoId);

            return $response === true;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error deleting video', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return false;
        }
    }

    /**
     * Lister toutes les vidéos de la bibliothèque
     */
    public function listVideos(int $page = 1, int $itemsPerPage = 100): ?array
    {
        try {
            $response = $this->bunnyStream->listVideos($page, $itemsPerPage);

            if ($response && is_array($response)) {
                return $response;
            }

            return null;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error listing videos', [
                'error' => $e->getMessage(),
                'page' => $page,
                'items_per_page' => $itemsPerPage,
            ]);

            return null;
        }
    }

    /**
     * Obtenir l'URL de lecture d'une vidéo
     */
    public function getPlaybackUrl(string $videoId): ?string
    {
        $video = $this->getVideo($videoId);

        if (! $video || ! isset($video['guid'])) {
            return null;
        }

        // Format de l'URL de lecture Bunny Stream
        $libraryId = config('services.bunny.stream_library_id');

        return "https://iframe.mediadelivery.net/embed/{$libraryId}/{$videoId}";
    }

    /**
     * Obtenir l'URL de la miniature d'une vidéo
     */
    public function getThumbnailUrl(string $videoId, int $width = 1280, int $height = 720): ?string
    {
        $video = $this->getVideo($videoId);

        if (! $video || ! isset($video['guid'])) {
            return null;
        }

        // Format de l'URL de miniature Bunny Stream
        $pullZone = config('services.bunny.stream_pull_zone');

        return "https://{$pullZone}.b-cdn.net/{$videoId}/thumbnail.jpg?width={$width}&height={$height}";
    }

    /**
     * Vérifier si une vidéo est prête pour la lecture
     */
    public function isVideoReady(string $videoId): bool
    {
        $video = $this->getVideo($videoId);

        if (! $video) {
            return false;
        }

        // Le statut "finished" indique que la vidéo est prête
        return isset($video['status']) && $video['status'] === 4; // 4 = Finished
    }

    /**
     * Obtenir les métadonnées d'une vidéo (durée, résolution, etc.)
     */
    public function getVideoMetadata(string $videoId): ?array
    {
        $video = $this->getVideo($videoId);

        if (! $video) {
            return null;
        }

        return [
            'duration' => $video['length'] ?? null,
            'width' => $video['width'] ?? null,
            'height' => $video['height'] ?? null,
            'size' => $video['storageSize'] ?? null,
            'status' => $video['status'] ?? null,
            'created_at' => $video['dateUploaded'] ?? null,
        ];
    }
}
