<?php

namespace App\Models;

use App\Enums\BlogPostType;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

/**
 * @property int $id
 * @property string $slug
 * @property int $title_translation_key_id
 * @property BlogPostType $type
 * @property int $category_id
 * @property int|null $cover_picture_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $categories_count
 * @property int|null $cover_pictures_count
 * @property int|null $contents_count
 * @property int|null $drafts_count
 * @property int|null $game_reviews_count
 * @property-read TranslationKey $titleTranslationKey
 * @property-read BlogCategory $category
 * @property-read Picture|null $coverPicture
 * @property-read Collection|BlogPostContent[] $contents
 * @property-read BlogPostDraft|null $draft
 * @property-read GameReview|null $gameReview
 */
class BlogPost extends Model implements Feedable
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title_translation_key_id',
        'type',
        'category_id',
        'cover_picture_id',
    ];

    protected $casts = [
        'slug' => 'string',
        'type' => BlogPostType::class,
    ];

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function titleTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'title_translation_key_id');
    }

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function coverPicture(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'cover_picture_id');
    }

    /**
     * @return HasMany<BlogPostContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(BlogPostContent::class)->orderBy('order');
    }

    /**
     * @return HasOne<BlogPostDraft, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(BlogPostDraft::class, 'original_blog_post_id');
    }

    /**
     * @return HasMany<BlogPostDraft, $this>
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(BlogPostDraft::class, 'original_blog_post_id');
    }

    /**
     * @return HasOne<GameReview, $this>
     */
    public function gameReview(): HasOne
    {
        return $this->hasOne(GameReview::class);
    }

    /**
     * Scope a query to only include published posts.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */

    /**
     * Scope a query to only include posts of a specific type.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get feed items for RSS feed
     *
     * @return Collection<int, BlogPost>
     */
    public static function getFeedItems(): Collection
    {
        return static::with([
            'titleTranslationKey.translations',
            'category.nameTranslationKey.translations',
            'coverPicture',
            'contents' => function ($query) {
                $query->where('content_type', BlogContentMarkdown::class)->orderBy('order');
            },
            'contents.content.translationKey.translations',
        ])
            ->orderBy('created_at', 'desc')
            ->limit(50) // Limit to 50 most recent posts
            ->get();
    }

    /**
     * Convert blog post to feed item
     */
    public function toFeedItem(): FeedItem
    {
        // Get translated title with fallback
        $title = $this->getTranslatedText($this->titleTranslationKey);

        // Extract excerpt from first markdown content
        $excerpt = $this->extractExcerpt();

        // Generate feed item
        $feedItem = FeedItem::create()
            ->id($this->id)
            ->title($title)
            ->summary($excerpt)
            ->updated($this->updated_at)
            ->link(route('public.blog.post', ['slug' => $this->slug]))
            ->authorName("Sofiane Lasri")
            ->authorEmail("sofianelasri@sl-projects.com");

        // Add category
        if ($this->category) {
            $categoryName = $this->getTranslatedText($this->category->nameTranslationKey);
            $feedItem->category($categoryName);
        }

        return $feedItem;
    }

    /**
     * Get translation with fallback
     */
    private function getTranslation(?TranslationKey $translationKey, string $locale, string $fallbackLocale): string
    {
        if (! $translationKey || ! $translationKey->translations) {
            return '';
        }

        // Try current locale first
        $translation = $translationKey->translations
            ->where('locale', $locale)
            ->first();

        if ($translation && ! empty($translation->text)) {
            return $translation->text;
        }

        // Fallback to fallback locale
        $translation = $translationKey->translations
            ->where('locale', $fallbackLocale)
            ->first();

        return $translation ? $translation->text : '';
    }

    /**
     * Get current locale and fallback locale
     *
     * @return array{locale: string, fallbackLocale: string}
     */
    private function getLocales(): array
    {
        return [
            'locale' => app()->getLocale(),
            'fallbackLocale' => config('app.fallback_locale', 'en'),
        ];
    }

    /**
     * Get translated text with automatic locale fallback
     */
    private function getTranslatedText(?TranslationKey $translationKey): string
    {
        $locales = $this->getLocales();

        return $this->getTranslation($translationKey, $locales['locale'], $locales['fallbackLocale']);
    }

    /**
     * Extract excerpt from first markdown content
     */
    private function extractExcerpt(int $maxLength = 200): string
    {
        // Get first markdown content
        $firstTextContent = $this->contents
            ->where('content_type', BlogContentMarkdown::class)
            ->sortBy('order')
            ->first();

        if (! $firstTextContent || ! $firstTextContent->content) {
            return '';
        }

        $markdownContent = $firstTextContent->content;

        // Ensure content is BlogContentMarkdown and has translation key
        if (! ($markdownContent instanceof BlogContentMarkdown)) {
            return '';
        }

        if (! $markdownContent->translationKey || ! $markdownContent->translationKey->translations) {
            return '';
        }

        // Get text content with fallback
        $text = $this->getTranslatedText($markdownContent->translationKey);

        if (empty($text)) {
            return '';
        }

        // Remove markdown formatting
        $plainText = strip_tags(str_replace(['#', '*', '_', '`'], '', $text));

        // Clean up whitespace
        $plainText = preg_replace('/\s+/', ' ', trim($plainText)) ?? '';

        if (strlen($plainText) <= $maxLength) {
            return $plainText;
        }

        // Truncate at word boundary
        $truncated = substr($plainText, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated.'...';
    }
}
