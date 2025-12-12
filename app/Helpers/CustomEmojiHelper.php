<?php

namespace App\Helpers;

use App\Models\CustomEmoji;
use Illuminate\Support\Facades\Storage;

class CustomEmojiHelper
{
    /**
     * Generate HTML picture tag for a custom emoji.
     */
    public static function generatePictureTag(CustomEmoji $emoji): string
    {
        $formats = config('emoji.formats', ['webp', 'png']);
        $variant = config('emoji.variant', 'thumbnail');

        $query = $emoji->picture->optimizedPictures()
            ->whereIn('format', $formats)
            ->where('variant', $variant);

        // Use FIELD() for MySQL, fall back to regular ordering for SQLite
        if (config('database.default') === 'mysql') {
            $placeholders = implode(',', array_fill(0, count($formats), '?'));
            $query->orderByRaw("FIELD(format, $placeholders)", $formats);
        } else {
            $query->orderBy('format');
        }

        $optimizedPictures = $query->get();

        if ($optimizedPictures->isEmpty()) {
            // Fallback to original if no optimized versions available
            if ($emoji->picture->path_original) {
                $url = Storage::url($emoji->picture->path_original);

                return sprintf(
                    '<img src="%s" alt="%s" class="inline-emoji" loading="lazy" />',
                    htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($emoji->name, ENT_QUOTES, 'UTF-8')
                );
            }

            // No image available at all
            return sprintf(':%s:', htmlspecialchars($emoji->name, ENT_QUOTES, 'UTF-8'));
        }

        // Build <picture> tag with multiple sources
        $sources = [];
        $fallbackUrl = null;

        foreach ($optimizedPictures as $optimized) {
            $url = Storage::url($optimized->path);

            if ($fallbackUrl === null) {
                $fallbackUrl = $url;
            }

            // Get MIME type for format
            $mimeType = self::getMimeType($optimized->format);

            $sources[] = sprintf(
                '<source srcset="%s" type="%s" />',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($mimeType, ENT_QUOTES, 'UTF-8')
            );
        }

        // Build final picture tag
        $sourcesHtml = implode('', $sources);
        $imgTag = sprintf(
            '<img src="%s" alt="%s" class="inline-emoji" loading="lazy" />',
            htmlspecialchars($fallbackUrl ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($emoji->name, ENT_QUOTES, 'UTF-8')
        );

        return sprintf(
            '<picture class="inline-emoji">%s%s</picture>',
            $sourcesHtml,
            $imgTag
        );
    }

    /**
     * Get MIME type for image format.
     */
    private static function getMimeType(string $format): string
    {
        return match ($format) {
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/'.$format,
        };
    }
}
