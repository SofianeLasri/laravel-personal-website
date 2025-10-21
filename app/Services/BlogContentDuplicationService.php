<?php

namespace App\Services;

use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BlogContentDuplicationService
{
    /**
     * Duplicate a markdown content with its translation key
     */
    public function duplicateMarkdownContent(BlogContentMarkdown $originalContent): BlogContentMarkdown
    {
        return DB::transaction(function () use ($originalContent) {
            // Duplicate the translation key with all its translations
            $translationKey = $originalContent->translationKey;
            if (!$translationKey) {
                throw new \RuntimeException('Markdown content missing translation key');
            }

            $newTranslationKey = $this->duplicateTranslationKey($translationKey);

            // Create new markdown content
            return BlogContentMarkdown::create([
                'translation_key_id' => $newTranslationKey->id,
            ]);
        });
    }

    /**
     * Duplicate a gallery content with its picture relationships
     */
    public function duplicateGalleryContent(BlogContentGallery $originalContent): BlogContentGallery
    {
        return DB::transaction(function () use ($originalContent) {
            // Create new gallery content
            $newGallery = BlogContentGallery::create([
                'layout' => $originalContent->layout,
                'columns' => $originalContent->columns,
            ]);

            // Duplicate picture relationships with their pivot data
            foreach ($originalContent->pictures as $picture) {
                $pivotData = [
                    'order' => $picture->pivot->order,
                ];

                // Duplicate caption translation key if it exists
                if ($picture->pivot->caption_translation_key_id) {
                    $captionTranslationKey = TranslationKey::with('translations')
                        ->find($picture->pivot->caption_translation_key_id);

                    if ($captionTranslationKey instanceof TranslationKey) {
                        $newCaptionTranslationKey = $this->duplicateTranslationKey($captionTranslationKey);
                        $pivotData['caption_translation_key_id'] = $newCaptionTranslationKey->id;
                    }
                }

                $newGallery->pictures()->attach($picture->id, $pivotData);
            }

            return $newGallery;
        });
    }

    /**
     * Duplicate a video content with its caption translation
     */
    public function duplicateVideoContent(BlogContentVideo $originalContent): BlogContentVideo
    {
        return DB::transaction(function () use ($originalContent) {
            $data = [
                'video_id' => $originalContent->video_id,
            ];

            // Duplicate caption translation key if it exists
            if ($originalContent->caption_translation_key_id) {
                $captionTranslationKey = $originalContent->captionTranslationKey;
                if (!$captionTranslationKey) {
                    throw new \RuntimeException('Video content missing caption translation key');
                }

                $newCaptionTranslationKey = $this->duplicateTranslationKey($captionTranslationKey);
                $data['caption_translation_key_id'] = $newCaptionTranslationKey->id;
            }

            return BlogContentVideo::create($data);
        });
    }

    /**
     * Duplicate all contents from one blog post to another
     *
     * @param  Collection<int, \App\Models\BlogPostContent|\App\Models\BlogPostDraftContent>  $originalContents
     * @return array<int, array{content_type: string, content_id: int, order: int}>
     */
    public function duplicateAllContents($originalContents): array
    {
        $newContents = [];

        foreach ($originalContents as $originalContent) {
            // Type guard: ensure we have the expected properties
            if (!isset($originalContent->content_type) || !isset($originalContent->content)) {
                continue;
            }

            $contentType = $originalContent->content_type;
            $content = $originalContent->content;

            // Skip if content is null
            if (!$content) {
                continue;
            }

            try {
                if ($contentType === BlogContentMarkdown::class && $content instanceof BlogContentMarkdown) {
                    $newContent = $this->duplicateMarkdownContent($content);
                } elseif ($contentType === BlogContentGallery::class && $content instanceof BlogContentGallery) {
                    $newContent = $this->duplicateGalleryContent($content);
                } elseif ($contentType === BlogContentVideo::class && $content instanceof BlogContentVideo) {
                    $newContent = $this->duplicateVideoContent($content);
                } else {
                    // Skip unknown content types
                    continue;
                }

                $newContents[] = [
                    'content_type' => $contentType,
                    'content_id' => $newContent->id,
                    'order' => $originalContent->order ?? 0,
                ];
            } catch (\Exception $e) {
                // Log error but continue with other contents
                \Log::warning('Failed to duplicate content', [
                    'content_type' => $contentType,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $newContents;
    }

    /**
     * Duplicate a translation key with all its translations
     */
    private function duplicateTranslationKey(TranslationKey $originalTranslationKey): TranslationKey
    {
        // Create new translation key with a unique key
        $newTranslationKey = TranslationKey::create([
            'key' => $this->generateUniqueTranslationKey($originalTranslationKey->key),
        ]);

        // Duplicate all translations
        foreach ($originalTranslationKey->translations as $translation) {
            $newTranslationKey->translations()->create([
                'locale' => $translation->locale,
                'text' => $translation->text,
            ]);
        }

        return $newTranslationKey;
    }

    /**
     * Generate a unique translation key based on the original key
     */
    private function generateUniqueTranslationKey(string $originalKey): string
    {
        $baseKey = $originalKey.'_copy';
        $key = $baseKey;
        $counter = 1;

        while (TranslationKey::where('key', $key)->exists()) {
            $key = $baseKey.'_'.$counter;
            $counter++;
        }

        return $key;
    }
}
