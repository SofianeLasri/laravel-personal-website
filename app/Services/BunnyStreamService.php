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
     * @return array{videoLibraryId:int,guid:string|null,title:string|null,description:string|null,dateUploaded:string,views:int,status:int,framerate:float,width:int,height:int,availableResolutions:string|null,outputCodecs:string|null,thumbnailCount:int,encodeProgress:int,storageSize:int,captions:array{srclang:string|null,label:string|null,version:int}|null,hasMP4FallBack:bool,collectionId:string|null,thumbnailFileName:string|null,averageWatchTime:string|null,totalWatchTime:int,category:string|null,chapters:array{title:string,start:int,end:int}|null,moments:array{label:string,timestamp:int}|null,metaTags:array{property:string|null,value:string|null}|null,transcodingMessages:array{timeStamp:string,issueCode:int}|null,jitEncodingEnabled:bool}|null
     */
    public function uploadVideo(string $title, string $filePath): ?array
    {
        try {
            /**
             * @var array{videoLibraryId:int,guid:string|null,title:string|null,description:string|null,dateUploaded:string,views:int,status:int,framerate:float,width:int,height:int,availableResolutions:string|null,outputCodecs:string|null,thumbnailCount:int,encodeProgress:int,storageSize:int,captions:array{srclang:string|null,label:string|null,version:int}|null,hasMP4FallBack:bool,collectionId:string|null,thumbnailFileName:string|null,averageWatchTime:string|null,totalWatchTime:int,category:string|null,chapters:array{title:string,start:int,end:int}|null,moments:array{label:string,timestamp:int}|null,metaTags:array{property:string|null,value:string|null}|null,transcodingMessages:array{timeStamp:string,issueCode:int}|null,jitEncodingEnabled:bool}|null $bunnyResponse
             */
            $bunnyResponse = $this->createVideo($title);

            if (empty($bunnyResponse) || empty($bunnyResponse['guid'])) {
                Log::error('BunnyStreamService: Failed to create video entry', is_array($bunnyResponse) ? $bunnyResponse : []);

                return null;
            }

            $videoId = $bunnyResponse['guid'];
            $uploadResult = $this->uploadVideoFile($videoId, $filePath);

            if (! $uploadResult) {
                Log::error('BunnyStreamService: Failed to upload video file', ['video_id' => $videoId]);
                $this->deleteVideo($videoId);

                return null;
            }

            Log::info('BunnyStreamService: Video uploaded successfully', ['video_id' => $videoId]);

            return $bunnyResponse;
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
     * @return array{videoLibraryId:int,guid:string|null,title:string|null,description:string|null,dateUploaded:string,views:int,status:int,framerate:float,width:int,height:int,availableResolutions:string|null,outputCodecs:string|null,thumbnailCount:int,encodeProgress:int,storageSize:int,captions:array{srclang:string|null,label:string|null,version:int}|null,hasMP4FallBack:bool,collectionId:string|null,thumbnailFileName:string|null,averageWatchTime:string|null,totalWatchTime:int,category:string|null,chapters:array{title:string,start:int,end:int}|null,moments:array{label:string,timestamp:int}|null,metaTags:array{property:string|null,value:string|null}|null,transcodingMessages:array{timeStamp:string,issueCode:int}|null,jitEncodingEnabled:bool}|null
     */
    public function createVideo(string $title): ?array
    {
        try {
            /**
             * @var array{videoLibraryId:int,guid:string|null,title:string|null,description:string|null,dateUploaded:string,views:int,status:int,framerate:float,width:int,height:int,availableResolutions:string|null,outputCodecs:string|null,thumbnailCount:int,encodeProgress:int,storageSize:int,captions:array{srclang:string|null,label:string|null,version:int}|null,hasMP4FallBack:bool,collectionId:string|null,thumbnailFileName:string|null,averageWatchTime:string|null,totalWatchTime:int,category:string|null,chapters:array{title:string,start:int,end:int}|null,moments:array{label:string,timestamp:int}|null,metaTags:array{property:string|null,value:string|null}|null,transcodingMessages:array{timeStamp:string,issueCode:int}|null,jitEncodingEnabled:bool} $bunnyResponse
             */
            $bunnyResponse = $this->bunnyStream->createVideo($title);

            if (empty($bunnyResponse['guid'])) {
                Log::error('BunnyStreamService: Invalid response when creating video', ['response' => $bunnyResponse]);

                return null;
            }

            return $bunnyResponse;
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
            /**
             * @var array{success:bool,message:string|null,statusCode:int} $bunnyResponse
             */
            $bunnyResponse = $this->bunnyStream->uploadVideo($videoId, $filePath);

            if ($bunnyResponse['success']) {
                return true;
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

    /**
     * @return array{
     *     videoLibraryId:int,
     *     guid:string|null,
     *     title:string|null,
     *     description:string|null,
     *     dateUploaded:string,
     *     views:int,
     *     isPublic:bool,
     *     length:int,
     *     status:int,
     *     framerate:float,
     *     width:int,
     *     height:int,
     *     availableResolutions:string|null,
     *     outputCodecs:string|null,
     *     thumbnailCount:int,
     *     encodeProgress:int,
     *     storageSize:int,
     *     captions:array{srclang:string|null,label:string|null,version:int}|null,
     *     hasMP4FallBack:bool,collectionId:string|null,
     *     collectionId:string|null,
     *     thumbnailFileName:string|null,
     *     averageWatchTime:string|null,
     *     totalWatchTime:int,category:string|null,
     *     category:string|null,
     *     chapters:array{title:string,start:int,end:int}|null,
     *     moments:array{label:string,timestamp:int}|null,
     *     metaTags:array{property:string|null,value:string|null}|null,
     *     transcodingMessages:array{timeStamp:string,issueCode:int}|null,
     *     jitEncodingEnabled:bool
     * }|null
     */
    public function getVideo(string $videoId): ?array
    {
        try {
            /** @phpstan-ignore-next-line */
            return $this->bunnyStream->getVideo($videoId);
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
    public function getPlaybackUrl(string $videoId): string
    {
        $libraryId = config('services.bunny.stream_library_id');

        return "https://iframe.mediadelivery.net/embed/$libraryId/$videoId";
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

        return "https://$pullZone.b-cdn.net/$videoId/thumbnail.jpg?width=$width&height=$height";
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

        return $video['status'] === 4; // 4 = Finished
    }

    /**
     * Returns metadata for a video.
     *
     * @param  string  $videoId  The GUID of the video.
     * @return array{duration:int,width:int,height:int,size:int,status:int,created_at:string}|null
     */
    public function getVideoMetadata(string $videoId): ?array
    {
        $video = $this->getVideo($videoId);

        if (! $video) {
            return null;
        }

        return [
            'duration' => $video['length'],
            'width' => $video['width'],
            'height' => $video['height'],
            'size' => $video['storageSize'],
            'status' => $video['status'],
            'created_at' => $video['dateUploaded'],
        ];
    }
}
