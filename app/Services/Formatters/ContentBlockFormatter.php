<?php

namespace App\Services\Formatters;

use App\Enums\ContentRenderContext;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\CreationContent;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Services\CustomEmojiResolverService;
use Exception;

class ContentBlockFormatter
{
    public function __construct(
        private readonly MediaFormatter $mediaFormatter,
        private readonly TranslationHelper $translationHelper,
        private readonly CustomEmojiResolverService $emojiResolver,
    ) {}

    /**
     * Format a single content block for SSR.
     * Handles ContentMarkdown, ContentGallery, and ContentVideo types.
     *
     * @return array{id: int, order: int, content_type: string, markdown?: string, gallery?: array{id: int, pictures: array<int, mixed>}, video?: array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string, caption: string|null}}
     */
    public function format(
        CreationContent|BlogPostContent|BlogPostDraftContent $content,
        ContentRenderContext $context = ContentRenderContext::PUBLIC,
    ): array {
        $result = [
            'id' => $content->id,
            'order' => $content->order,
            'content_type' => $content->content_type,
        ];

        $contentType = $content->content_type;
        $contentModel = $content->content;

        if ($contentType === ContentMarkdown::class && $contentModel instanceof ContentMarkdown) {
            $result['markdown'] = $this->formatMarkdown($contentModel);
        } elseif ($contentType === ContentGallery::class && $contentModel instanceof ContentGallery) {
            $result['gallery'] = $this->formatGallery($contentModel);
        } elseif ($contentType === ContentVideo::class && $contentModel instanceof ContentVideo) {
            $videoResult = $this->formatVideo($contentModel, $context);
            if ($videoResult !== null) {
                $result['video'] = $videoResult;
            }
        }

        return $result;
    }

    /**
     * Format a ContentMarkdown block.
     */
    private function formatMarkdown(ContentMarkdown $contentMarkdown): string
    {
        $markdownContent = $contentMarkdown->translationKey
            ? $this->translationHelper->getWithFallback($contentMarkdown->translationKey->translations)
            : '';

        try {
            return $this->emojiResolver->resolveEmojisInMarkdown($markdownContent);
        } catch (Exception) {
            return $markdownContent;
        }
    }

    /**
     * Format a ContentGallery block.
     *
     * @return array{id: int, pictures: array<int, mixed>}
     */
    private function formatGallery(ContentGallery $contentGallery): array
    {
        $captionTranslationKeyIds = $contentGallery->pictures
            ->pluck('pivot.caption_translation_key_id')
            ->filter()
            ->unique();

        $captionTranslations = [];
        if ($captionTranslationKeyIds->isNotEmpty()) {
            $translationKeys = TranslationKey::with('translations')
                ->whereIn('id', $captionTranslationKeyIds)
                ->get()
                ->keyBy('id');

            foreach ($translationKeys as $key => $translationKey) {
                $captionTranslations[$key] = $this->translationHelper->getWithFallback($translationKey->translations);
            }
        }

        return [
            'id' => $contentGallery->id,
            'pictures' => $contentGallery->pictures->map(function (Picture $picture) use ($captionTranslations) {
                $formattedPicture = $this->mediaFormatter->formatPicture($picture);

                /** @phpstan-ignore property.notFound */
                $captionTranslationKeyId = $picture->pivot?->caption_translation_key_id;
                if ($captionTranslationKeyId && isset($captionTranslations[$captionTranslationKeyId])) {
                    $formattedPicture['caption'] = $captionTranslations[$captionTranslationKeyId];
                }

                return $formattedPicture;
            })->toArray(),
        ];
    }

    /**
     * Format a ContentVideo block.
     *
     * @return array{id: int, bunnyVideoId: string, name: string, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}}, libraryId: string, caption: string|null}|null
     */
    private function formatVideo(ContentVideo $contentVideo, ContentRenderContext $context): ?array
    {
        $video = $contentVideo->video;

        $videoIsReady = $video && $video->status === VideoStatus::READY;
        $videoIsVisible = $context === ContentRenderContext::PREVIEW
            || ($video && $video->visibility === VideoVisibility::PUBLIC);

        if (! $videoIsReady || ! $videoIsVisible) {
            return null;
        }

        $caption = null;
        if ($contentVideo->captionTranslationKey) {
            $caption = $this->translationHelper->getWithFallback($contentVideo->captionTranslationKey->translations);
        }

        $formattedVideo = $this->mediaFormatter->formatVideo($video);
        $formattedVideo['caption'] = $caption;

        return $formattedVideo;
    }
}
