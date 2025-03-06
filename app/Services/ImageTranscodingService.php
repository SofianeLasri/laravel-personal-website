<?php

namespace App\Services;

use Imagick;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\RuntimeException;
use Intervention\Image\ImageManager;
use Log;

class ImageTranscodingService
{
    protected ImageManager $imageManager;

    public function __construct(ImagickDriver $driver)
    {
        $this->imageManager = new ImageManager($driver);
    }

    /**
     * Transcode an image to a new resolution
     *
     * @param  string  $source  The source image path or content. Eg: /path/to/image.jpg or file_get_contents('/path/to/image.jpg')
     * @param  int|null  $resolution  The new resolution to transcode the image to
     * @param  string  $codec  The codec to use for transcoding. Eg: jpeg, webp, png, avif
     * @return string|null The transcoded image content
     */
    public function transcode(string $source, ?int $resolution = null, string $codec = 'avif'): ?string
    {
        $image = $this->imageManager->read($source);
        try {
            $imageArea = $image->width() * $image->height();
            $maxArea = Imagick::getResourceLimit(Imagick::RESOURCETYPE_AREA);
            $imageWidth = $image->width();
            $maxWidth = config('app.imagick.max_width');
            $imageHeight = $image->height();
            $maxHeight = config('app.imagick.max_height');

            if ($imageWidth > $maxWidth || $imageHeight > $maxHeight || $imageArea > $maxArea) {
                Log::error('Image resolution exceeds maximum allowed resolution', [
                    'image_height' => $image->height(),
                    'image_width' => $image->width(),
                    'image_surface' => $image->width() * $image->height(),
                    'max_width' => config('app.imagick.max_width'),
                    'max_height' => config('app.imagick.max_height'),
                    'max_surface' => Imagick::getResourceLimit(Imagick::RESOURCETYPE_AREA),
                ]);

                return null;
            }

            if ($resolution) {
                $image->scale($resolution);
            }

            echo "Encoding to format: $codec\n";

            $encodedPicture = match ($codec) {
                'jpeg' => $image->encode(new JpegEncoder(quality: 85))->toString(),
                'webp' => $image->encode(new WebpEncoder(quality: 85))->toString(),
                'png' => $image->encode(new PngEncoder)->toString(),
                default => $image->encode(new AvifEncoder(quality: 85))->toString(),
            };

            if ($encodedPicture === false) {
                echo "Failed to encode image\n";
                Log::error('Failed to encode image', [
                    'image' => $image,
                    'codec' => $codec,
                ]);

                return null;
            }

            if (empty($encodedPicture)) {
                echo "Empty encoded image\n";
                Log::error('Empty encoded image', [
                    'image' => $image,
                    'codec' => $codec,
                ]);

                return null;
            }

            return $encodedPicture;
        } catch (RuntimeException $exception) {
            Log::error('Failed to transcode image', [
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * Get the dimensions of an image
     *
     * @param  string  $source  The source image path or content. Eg: /path/to/image.jpg or file_get_contents('/path/to/image.jpg')
     * @return array{width: int, height: int}
     */
    public function getDimensions(string $source): array
    {
        $image = $this->imageManager->read($source);

        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }
}
