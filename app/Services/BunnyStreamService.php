<?php

namespace App\Services;

use Corbpie\BunnyCdn\BunnyAPIStream;
use Exception;
use Illuminate\Support\Facades\Log;

class BunnyStreamService
{
    public function __construct(protected BunnyAPIStream $bunnyStream)
    {
        $this->bunnyStream->apiKey(config('services.bunny.stream_api_key'));
        $this->bunnyStream->streamLibraryAccessKey(config('services.bunny.stream_api_key'));
        $this->bunnyStream->setStreamLibraryId((int) config('services.bunny.stream_library_id'));
    }

    /**
     * Uploads a video to Bunny Stream. Makes both a video entry and uploads the file.
     *
     * @param  string  $title  The name that will be shown in the Bunny Stream library.
     * @param  string  $filePath  The path to the video file to be uploaded.
     */
    public function uploadVideo(string $title, string $filePath): ?array
    {
        try {
            // Create video entry in Bunny Stream
            $createdVideoData = $this->createVideo($title);

            if (! empty($createdVideoData) && ! isset($createdVideoData['guid'])) {
                Log::error('BunnyStreamService: Failed to create video entry');

                return null;
            }

            $videoId = $createdVideoData['guid'];
            $uploadResult = $this->uploadVideoFile($videoId, $filePath);

            if (! $uploadResult) {
                Log::error('BunnyStreamService: Failed to upload video file', ['video_id' => $videoId]);
                $this->deleteVideo($videoId);

                return null;
            }

            Log::info('BunnyStreamService: Video uploaded successfully', ['video_id' => $videoId]);

            return $createdVideoData;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error uploading video', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);

            return null;
        }
    }

    /**
     * Creates a video entry in Bunny Stream.
     *
     * **Note:** This method only creates the video entry and returns the GUID. The actual video file must be uploaded separately using `uploadVideoFile()`.
     *
     * @param  string  $title  The name that will be shown in the Bunny Stream library.
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
     * Uploads a video file to Bunny Stream. This method assumes that a video entry has already been created.
     *
     * **Note:** The video entry must be created first using `createVideo()`, which returns a GUID that is needed to upload the file.
     *
     * @param  string  $videoId  The GUID of the video entry created in Bunny Stream.
     * @param  string  $filePath  The path to the video file to be uploaded.
     */
    public function uploadVideoFile(string $videoId, string $filePath): bool
    {
        try {
            $response = $this->bunnyStream->uploadVideo($videoId, $filePath);

            if ($response && isset($response['success'])) {
                if ($response['success']) {
                    return true;
                }

                Log::warning('BunnyStreamService: Failed to upload video file', ['response' => $response]);
            }

            return false;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error uploading video file', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
                'file_path' => $filePath,
            ]);

            return false;
        }
    }

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
     * Deletes a video from Bunny Stream.
     *
     * @param  string  $videoId  The GUID of the video to be deleted.
     */
    public function deleteVideo(string $videoId): bool
    {
        try {
            $response = $this->bunnyStream->deleteVideo($videoId);
            if ($response && isset($response['success'])) {
                if ($response['success']) {
                    return true;
                }

                Log::warning('BunnyStreamService: Failed to delete video', ['response' => $response]);
            }

            return false;
        } catch (Exception $e) {
            Log::error('BunnyStreamService: Error deleting video', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return false;
        }
    }

    /**
     * Gets the playback URL for a video.
     *
     * @param  string  $videoId  The GUID of the video.
     */
    public function getPlaybackUrl(string $videoId): ?string
    {
        $video = $this->getVideo($videoId);

        if (! $video || ! isset($video['guid'])) {
            return null;
        }

        $libraryId = config('services.bunny.stream_library_id');

        return "https://iframe.mediadelivery.net/embed/{$libraryId}/{$videoId}";
    }

    /**
     * Gets the thumbnail URL for a video.
     *
     * @param  string  $videoId  The GUID of the video.
     * @param  int  $width  Width of the thumbnail image.
     * @param  int  $height  Height of the thumbnail image.
     */
    public function getThumbnailUrl(string $videoId, int $width = 1280, int $height = 720): ?string
    {
        $video = $this->getVideo($videoId);

        if (! $video || ! isset($video['guid'])) {
            return null;
        }

        $pullZone = config('services.bunny.stream_pull_zone');

        return "https://{$pullZone}.b-cdn.net/{$videoId}/thumbnail.jpg?width={$width}&height={$height}";
    }

    /**
     * Checks if the video transcoding is complete.
     *
     * @param  string  $videoId  The GUID of the video.
     */
    public function isVideoReady(string $videoId): bool
    {
        $video = $this->getVideo($videoId);

        if (! $video) {
            return false;
        }

        return isset($video['status']) && $video['status'] === 4; // 4 = Finished
    }

    /**
     * Returns metadata for a video.
     *
     * @param  string  $videoId  The GUID of the video.
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
