<?php

namespace App\Services;

use App\Helpers\CustomEmojiHelper;
use App\Models\CustomEmoji;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CustomEmojiResolverService
{
    /** @var Collection<string, CustomEmoji>|null */
    private ?Collection $emojisCache = null;

    /**
     * Resolve custom emoji references in markdown content.
     * Replaces :emoji_name: with HTML picture tags.
     */
    public function resolveEmojisInMarkdown(string $markdown): string
    {
        // Find all :emoji_name: patterns
        $pattern = '/:([a-zA-Z0-9_]+):/';

        if (! preg_match($pattern, $markdown)) {
            // No emojis found, return as-is
            return $markdown;
        }

        // Load all custom emojis if not cached
        if ($this->emojisCache === null) {
            $this->loadEmojis();
        }

        // Replace each :emoji_name: with its HTML
        $resolved = preg_replace_callback(
            $pattern,
            function (array $matches) {
                $emojiName = $matches[1];

                // Check if this emoji exists
                $emoji = $this->emojisCache?->get($emojiName);

                if ($emoji === null) {
                    // Emoji not found, leave as-is
                    return $matches[0];
                }

                // Generate HTML picture tag
                return CustomEmojiHelper::generatePictureTag($emoji);
            },
            $markdown
        );

        return $resolved ?? $markdown;
    }

    /**
     * Load all custom emojis into cache.
     */
    private function loadEmojis(): void
    {
        // Try to get from Laravel cache first (5 minutes)
        $this->emojisCache = Cache::remember(
            'custom_emojis_all',
            now()->addMinutes(5),
            function () {
                return CustomEmoji::with(['picture.optimizedPictures'])
                    ->get()
                    ->keyBy('name');
            }
        );
    }

    /**
     * Clear the emojis cache.
     * Call this when emojis are added/updated/deleted.
     */
    public static function clearCache(): void
    {
        Cache::forget('custom_emojis_all');
    }

    /**
     * Resolve emojis in multiple markdown texts at once.
     * More efficient than calling resolveEmojisInMarkdown multiple times.
     *
     * @param  array<string>  $markdownTexts
     * @return array<string>
     */
    public function resolveEmojisInBatch(array $markdownTexts): array
    {
        // Preload emojis once for the batch
        if ($this->emojisCache === null) {
            $this->loadEmojis();
        }

        $resolved = [];
        foreach ($markdownTexts as $key => $markdown) {
            $resolved[$key] = $this->resolveEmojisInMarkdown($markdown);
        }

        return $resolved;
    }
}
