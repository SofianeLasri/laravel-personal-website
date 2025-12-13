<?php

namespace App\Services\Formatters;

use App\Enums\BlogPostType;
use App\Enums\CategoryColor;
use App\Enums\ContentRenderContext;
use App\Enums\GameReviewRating;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentMarkdown;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

readonly class BlogPostFormatter
{
    public function __construct(
        private MediaFormatter $mediaFormatter,
        private TranslationHelper $translationHelper,
        private ContentBlockFormatter $contentBlockFormatter,
    ) {}

    /**
     * Format the BlogPost model for SSR short view (index cards).
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string|null,
     *     excerpt: string
     * }
     */
    public function formatShort(BlogPost $blogPost): array
    {
        return $this->formatBasicPost($blogPost, 150);
    }

    /**
     * Format the BlogPost model for SSR hero view (featured post).
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string|null,
     *     excerpt: string
     * }
     */
    public function formatHero(BlogPost $blogPost): array
    {
        return $this->formatBasicPost($blogPost, 300);
    }

    /**
     * Format the BlogPost model for SSR full view (article page).
     *
     * @return array<string, mixed>
     */
    public function formatFull(BlogPost $blogPost): array
    {
        /** @phpstan-ignore ternary.alwaysTrue */
        $title = $blogPost->titleTranslationKey
            ? $this->translationHelper->getWithFallback($blogPost->titleTranslationKey->translations)
            : '';
        $categoryName = $blogPost->category->nameTranslationKey
            ? $this->translationHelper->getWithFallback($blogPost->category->nameTranslationKey->translations)
            : '';

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $contents */
        $contents = $blogPost->contents->map(
            fn ($content) => $this->contentBlockFormatter->format($content)
        );

        $excerpt = $this->extractExcerptFromFormattedContents($contents);

        $result = [
            'id' => $blogPost->id,
            'title' => $title,
            'slug' => $blogPost->slug,
            'type' => $blogPost->type,
            'category' => [
                'name' => $categoryName,
                'color' => $blogPost->category->color,
            ],
            'coverImage' => $blogPost->coverPicture
                ? $this->mediaFormatter->formatPicture($blogPost->coverPicture)
                : null,
            'publishedAt' => $blogPost->created_at,
            'publishedAtFormatted' => $this->formatFullDate($blogPost->created_at),
            'excerpt' => $excerpt,
            'contents' => $contents->toArray(),
        ];

        if ($blogPost->type === BlogPostType::GAME_REVIEW && $blogPost->gameReview) {
            $result['gameReview'] = $this->formatGameReview($blogPost->gameReview);
        }

        return $result;
    }

    /**
     * Format a BlogPostDraft for preview.
     *
     * @return array<string, mixed>
     */
    public function formatDraftFull(BlogPostDraft $draft): array
    {
        $title = $draft->titleTranslationKey
            ? $this->translationHelper->getWithFallback($draft->titleTranslationKey->translations)
            : '';
        $categoryName = $draft->category->nameTranslationKey
            ? $this->translationHelper->getWithFallback($draft->category->nameTranslationKey->translations)
            : '';

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $contents */
        $contents = $draft->contents->map(
            fn ($content) => $this->contentBlockFormatter->format($content, ContentRenderContext::PREVIEW)
        );

        $excerpt = $this->extractExcerptFromFormattedContents($contents);

        $result = [
            'id' => $draft->id,
            'title' => $title,
            'slug' => $draft->slug,
            'type' => $draft->type,
            'category' => [
                'name' => $categoryName,
                'color' => $draft->category->color,
            ],
            'coverImage' => $draft->coverPicture
                ? $this->mediaFormatter->formatPicture($draft->coverPicture)
                : null,
            'publishedAt' => $draft->created_at,
            'publishedAtFormatted' => $this->formatFullDate($draft->created_at),
            'excerpt' => $excerpt,
            'contents' => $contents->toArray(),
            'isPreview' => true,
        ];

        if ($draft->type === BlogPostType::GAME_REVIEW && $draft->gameReviewDraft) {
            $result['gameReview'] = $this->formatGameReview($draft->gameReviewDraft);
        }

        return $result;
    }

    /**
     * Format a game review for SSR.
     *
     * @return array{gameTitle: string, releaseDate: Carbon|null, genre: string|null, developer: string|null, publisher: string|null, platforms: array<string, mixed>|null, rating: GameReviewRating|null, pros: string|null, cons: string|null, coverPicture: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null}
     */
    public function formatGameReview(GameReview|GameReviewDraft $gameReview): array
    {
        $pros = $gameReview->prosTranslationKey
            ? $this->translationHelper->getWithFallback($gameReview->prosTranslationKey->translations)
            : null;
        $cons = $gameReview->consTranslationKey
            ? $this->translationHelper->getWithFallback($gameReview->consTranslationKey->translations)
            : null;

        return [
            'gameTitle' => $gameReview->game_title,
            'releaseDate' => $gameReview->release_date,
            'genre' => $gameReview->genre,
            'developer' => $gameReview->developer,
            'publisher' => $gameReview->publisher,
            'platforms' => $gameReview->platforms,
            'rating' => $gameReview->rating,
            'pros' => $pros,
            'cons' => $cons,
            'coverPicture' => $gameReview->coverPicture
                ? $this->mediaFormatter->formatPicture($gameReview->coverPicture)
                : null,
        ];
    }

    /**
     * Extract excerpt from the first text block of a blog post.
     *
     * @param  Collection<int, BlogPostContent>|Collection<int, BlogPostDraftContent>  $contents  The blog post contents
     * @param  int  $maxLength  Maximum length of excerpt
     */
    public function extractExcerptFromFirstTextBlock(Collection $contents, int $maxLength = 200): string
    {
        $firstTextContent = $contents
            ->where('content_type', ContentMarkdown::class)
            ->sortBy('order')
            ->first();

        if (! $firstTextContent || ! $firstTextContent->content) {
            return '';
        }

        $markdownContent = $firstTextContent->content;

        if (! $markdownContent instanceof ContentMarkdown) {
            return '';
        }

        if (! $markdownContent->translationKey) {
            return '';
        }

        $text = $this->translationHelper->getWithFallback($markdownContent->translationKey->translations);

        return $this->truncateText($text, $maxLength);
    }

    /**
     * Format basic post data (shared between short and hero formats).
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     slug: string,
     *     type: BlogPostType,
     *     category: array{name: string, color: CategoryColor},
     *     coverImage: array{filename: string, width: int|null, height: int|null, avif: array{thumbnail: string, small: string, medium: string, large: string, full: string}, webp: array{thumbnail: string, small: string, medium: string, large: string, full: string}, jpg: array{thumbnail: string, small: string, medium: string, large: string, full: string}}|null,
     *     publishedAt: Carbon|null,
     *     publishedAtFormatted: string|null,
     *     excerpt: string
     * }
     */
    private function formatBasicPost(BlogPost $blogPost, int $excerptLength): array
    {
        /** @phpstan-ignore ternary.alwaysTrue */
        $title = $blogPost->titleTranslationKey
            ? $this->translationHelper->getWithFallback($blogPost->titleTranslationKey->translations)
            : '';
        $categoryName = $blogPost->category->nameTranslationKey
            ? $this->translationHelper->getWithFallback($blogPost->category->nameTranslationKey->translations)
            : '';
        $excerpt = $this->extractExcerptFromFirstTextBlock($blogPost->contents, $excerptLength);

        return [
            'id' => $blogPost->id,
            'title' => $title,
            'slug' => $blogPost->slug,
            'type' => $blogPost->type,
            'category' => [
                'name' => $categoryName,
                'color' => $blogPost->category->color,
            ],
            'coverImage' => $blogPost->coverPicture
                ? $this->mediaFormatter->formatPicture($blogPost->coverPicture)
                : null,
            'publishedAt' => $blogPost->created_at,
            'publishedAtFormatted' => $this->translationHelper->formatDate($blogPost->created_at),
            'excerpt' => $excerpt,
        ];
    }

    /**
     * Extract excerpt from already formatted contents.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $formattedContents
     */
    private function extractExcerptFromFormattedContents(\Illuminate\Support\Collection $formattedContents): string
    {
        $firstMarkdownContent = $formattedContents->first(function ($content) {
            return $content['content_type'] === ContentMarkdown::class;
        });

        if ($firstMarkdownContent && isset($firstMarkdownContent['markdown'])) {
            return Str::limit(strip_tags($firstMarkdownContent['markdown']), 200);
        }

        return '';
    }

    /**
     * Format a date with full format (e.g., "15 janvier 2024").
     */
    private function formatFullDate(?Carbon $date): string
    {
        if (! $date) {
            return '';
        }

        $date->locale($this->translationHelper->getLocale());

        return $date->translatedFormat('j F Y');
    }

    /**
     * Truncate text at word boundary with ellipsis.
     */
    private function truncateText(string $text, int $maxLength): string
    {
        if (empty($text)) {
            return '';
        }

        $plainText = strip_tags(str_replace(['#', '*', '_', '`'], '', $text));
        $plainText = preg_replace('/\s+/', ' ', trim($plainText)) ?? '';

        if (strlen($plainText) <= $maxLength) {
            return $plainText;
        }

        $truncated = substr($plainText, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated.'...';
    }
}
