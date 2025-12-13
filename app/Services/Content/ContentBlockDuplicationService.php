<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\BlogPostContent;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\CreationContent;
use App\Models\CreationDraftContent;
use App\Models\TranslationKey;
use App\Services\Translation\TranslationKeyDuplicationService;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Log;
use RuntimeException;

/**
 * Service for duplicating content blocks with their translation keys.
 * Used during draft-to-published and published-to-draft conversions.
 */
readonly class ContentBlockDuplicationService
{
    public function __construct(
        private TranslationKeyDuplicationService $translationKeyDuplication
    ) {}

    /**
     * Duplicate a markdown content with its translation key
     */
    public function duplicateMarkdownContent(ContentMarkdown $originalContent): ContentMarkdown
    {
        return DB::transaction(function () use ($originalContent) {
            $translationKey = $originalContent->translationKey;
            if (! $translationKey) {
                throw new RuntimeException('Markdown content missing translation key');
            }

            $newTranslationKey = $this->translationKeyDuplication->duplicateForCopy($translationKey);

            return ContentMarkdown::create([
                'translation_key_id' => $newTranslationKey->id,
            ]);
        });
    }

    /**
     * Duplicate a gallery content with its picture relationships
     */
    public function duplicateGalleryContent(ContentGallery $originalContent): ContentGallery
    {
        return DB::transaction(function () use ($originalContent) {
            $newGallery = ContentGallery::create([
                'layout' => $originalContent->layout,
                'columns' => $originalContent->columns,
            ]);

            foreach ($originalContent->pictures as $picture) {
                $pivotData = [
                    'order' => $picture->pivot->order,
                ];

                if ($picture->pivot->caption_translation_key_id) {
                    $captionTranslationKey = TranslationKey::with('translations')
                        ->find($picture->pivot->caption_translation_key_id);

                    if ($captionTranslationKey instanceof TranslationKey) {
                        $newCaptionTranslationKey = $this->translationKeyDuplication->duplicateForCopy($captionTranslationKey);
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
    public function duplicateVideoContent(ContentVideo $originalContent): ContentVideo
    {
        return DB::transaction(function () use ($originalContent) {
            $data = [
                'video_id' => $originalContent->video_id,
            ];

            if ($originalContent->caption_translation_key_id) {
                $captionTranslationKey = $originalContent->captionTranslationKey;
                if (! $captionTranslationKey) {
                    throw new RuntimeException('Video content missing caption translation key');
                }

                $newCaptionTranslationKey = $this->translationKeyDuplication->duplicateForCopy($captionTranslationKey);
                $data['caption_translation_key_id'] = $newCaptionTranslationKey->id;
            }

            return ContentVideo::create($data);
        });
    }

    /**
     * Duplicate all contents from a collection
     *
     * @param  Collection<int, BlogPostContent|BlogPostDraftContent|CreationContent|CreationDraftContent>  $originalContents
     * @return array<int, array{content_type: string, content_id: int, order: int}>
     */
    public function duplicateAllContents($originalContents): array
    {
        $newContents = [];

        foreach ($originalContents as $originalContent) {
            if (! isset($originalContent->content_type) || ! isset($originalContent->content)) {
                continue;
            }

            $contentType = $originalContent->content_type;
            $content = $originalContent->content;

            if (! $content) {
                continue;
            }

            try {
                if ($contentType === ContentMarkdown::class && $content instanceof ContentMarkdown) {
                    $newContent = $this->duplicateMarkdownContent($content);
                } elseif ($contentType === ContentGallery::class && $content instanceof ContentGallery) {
                    $newContent = $this->duplicateGalleryContent($content);
                } elseif ($contentType === ContentVideo::class && $content instanceof ContentVideo) {
                    $newContent = $this->duplicateVideoContent($content);
                } else {
                    continue;
                }

                $newContents[] = [
                    'content_type' => $contentType,
                    'content_id' => $newContent->id,
                    'order' => $originalContent->order ?? 0,
                ];
            } catch (Exception $e) {
                Log::warning('Failed to duplicate content', [
                    'content_type' => $contentType,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return $newContents;
    }
}
