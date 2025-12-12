<?php

namespace App\Services\Formatters;

use App\Models\Picture;
use App\Models\Video;

class MediaFormatter
{
    /**
     * Format the Picture model for Server-Side Rendering (SSR).
     * Returns a SSRPicture TypeScript type compatible array.
     *
     * @return array{
     *     filename: string,
     *     width: int|null,
     *     height: int|null,
     *     avif: array{thumbnail: string, small: string, medium: string, large: string, full: string},
     *     webp: array{thumbnail: string, small: string, medium: string, large: string, full: string},
     *     jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}
     * }
     */
    public function formatPicture(Picture $picture): array
    {
        return [
            'filename' => $picture->filename,
            'width' => $picture->width,
            'height' => $picture->height,
            'avif' => $this->formatPictureVariants($picture, 'avif'),
            'webp' => $this->formatPictureVariants($picture, 'webp'),
            'jpg' => $this->formatPictureVariants($picture, 'jpg'),
        ];
    }

    /**
     * Format picture variants for a specific format.
     *
     * @return array{thumbnail: string, small: string, medium: string, large: string, full: string}
     */
    private function formatPictureVariants(Picture $picture, string $format): array
    {
        return [
            'thumbnail' => $picture->getUrl('thumbnail', $format),
            'small' => $picture->getUrl('small', $format),
            'medium' => $picture->getUrl('medium', $format),
            'large' => $picture->getUrl('large', $format),
            'full' => $picture->getUrl('full', $format),
        ];
    }

    /**
     * Format the Video model for Server-Side Rendering (SSR).
     * Returns a SSRVideo TypeScript type compatible array.
     *
     * @return array{
     *     id: int,
     *     bunnyVideoId: string,
     *     name: string,
     *     coverPicture: array{
     *         filename: string,
     *         width: int|null,
     *         height: int|null,
     *         avif: array{thumbnail: string, small: string, medium: string, large: string, full: string},
     *         webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}
     *     },
     *     libraryId: string
     * }
     */
    public function formatVideo(Video $video): array
    {
        /** @var Picture $coverPicture */
        $coverPicture = $video->coverPicture;

        return [
            'id' => $video->id,
            'bunnyVideoId' => $video->bunny_video_id,
            'name' => $video->name,
            'coverPicture' => $this->formatPicture($coverPicture),
            'libraryId' => config('services.bunny.stream_library_id'),
        ];
    }
}
