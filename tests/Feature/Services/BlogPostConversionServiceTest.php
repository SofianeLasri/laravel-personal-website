<?php

namespace Tests\Feature\Services;

use App\Enums\BlogPostType;
use App\Enums\GameReviewRating;
use App\Models\BlogCategory;
use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogContentVideo;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use App\Models\GameReviewDraftLink;
use App\Models\GameReviewLink;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\BlogPostConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogPostConversionService::class)]
class BlogPostConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlogPostConversionService $conversionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversionService = app(BlogPostConversionService::class);
    }

    public function test_draft_content_is_isolated_from_published_content()
    {
        // Create a blog post with content
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        // Create markdown content for the blog post
        $markdownKey = TranslationKey::factory()->create(['key' => 'original.content']);
        $markdownKey->translations()->create(['locale' => 'en', 'text' => 'Original content']);

        $markdownContent = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        // Create draft from published post
        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        // Verify draft has independent content
        $this->assertCount(1, $draft->contents);
        $draftContent = $draft->contents->first();

        // Assert content IDs are different (content was duplicated)
        $this->assertNotEquals($markdownContent->id, $draftContent->content_id);

        // Verify draft content is independent
        $draftMarkdownContent = $draftContent->content;
        $this->assertInstanceOf(BlogContentMarkdown::class, $draftMarkdownContent);
        $this->assertNotEquals($markdownContent->id, $draftMarkdownContent->id);

        // Verify translation keys are different
        $this->assertNotEquals($markdownKey->id, $draftMarkdownContent->translation_key_id);

        // Verify translation content is copied but independent
        $draftTranslationKey = $draftMarkdownContent->translationKey;
        $this->assertEquals('Original content', $draftTranslationKey->translations->first()->text);
        $this->assertStringContainsString('original.content', $draftTranslationKey->key);
        $this->assertStringContainsString('copy', $draftTranslationKey->key);
    }

    public function test_editing_draft_does_not_affect_published_content()
    {
        // Create published post with content
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $markdownKey = TranslationKey::factory()->create(['key' => 'original.content']);
        $markdownKey->translations()->create(['locale' => 'en', 'text' => 'Original content']);

        $markdownContent = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        // Create draft from published post
        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        // Edit the draft content
        $draftMarkdownContent = $draft->contents->first()->content;
        $draftTranslationKey = $draftMarkdownContent->translationKey;
        $draftTranslationKey->translations->first()->update(['text' => 'Modified draft content']);

        // Verify original content is unchanged
        $markdownKey->refresh();
        $this->assertEquals('Original content', $markdownKey->translations->first()->text);

        // Verify draft content is changed
        $draftTranslationKey->refresh();
        $this->assertEquals('Modified draft content', $draftTranslationKey->translations->first()->text);
    }

    public function test_publishing_draft_creates_independent_published_content()
    {
        // Create a draft with content
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Draft Title']);

        $draft = BlogPostDraft::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $markdownKey = TranslationKey::factory()->create(['key' => 'draft.content']);
        $markdownKey->translations()->create(['locale' => 'en', 'text' => 'Draft content']);

        $markdownContent = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        // Publish the draft
        $publishedPost = $this->conversionService->convertDraftToBlogPost($draft);

        // Verify published post has independent content
        $this->assertCount(1, $publishedPost->contents);
        $publishedContent = $publishedPost->contents->first();

        // Assert content IDs are different (content was duplicated)
        $this->assertNotEquals($markdownContent->id, $publishedContent->content_id);

        // Verify published content is independent
        $publishedMarkdownContent = $publishedContent->content;
        $this->assertInstanceOf(BlogContentMarkdown::class, $publishedMarkdownContent);
        $this->assertNotEquals($markdownContent->id, $publishedMarkdownContent->id);

        // Verify translation keys are different
        $this->assertNotEquals($markdownKey->id, $publishedMarkdownContent->translation_key_id);

        // Edit the draft content after publication
        $markdownKey->translations->first()->update(['text' => 'Modified draft after publication']);

        // Verify published content is unaffected
        $publishedTranslationKey = $publishedMarkdownContent->translationKey;
        $this->assertEquals('Draft content', $publishedTranslationKey->translations->first()->text);
    }

    public function test_updating_existing_published_post_cleans_up_old_content()
    {
        // Create existing published post with content
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'existing.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Existing Title']);

        $existingPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $oldMarkdownKey = TranslationKey::factory()->create(['key' => 'old.content']);
        $oldMarkdownKey->translations()->create(['locale' => 'en', 'text' => 'Old content']);

        $oldMarkdownContent = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $oldMarkdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $existingPost->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $oldMarkdownContent->id,
            'order' => 1,
        ]);

        // Create draft linked to existing post
        $draftTitleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $draftTitleKey->translations()->create(['locale' => 'en', 'text' => 'Updated Title']);

        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $existingPost->id,
            'title_translation_key_id' => $draftTitleKey->id,
            'category_id' => $category->id,
        ]);

        $newMarkdownKey = TranslationKey::factory()->create(['key' => 'new.content']);
        $newMarkdownKey->translations()->create(['locale' => 'en', 'text' => 'New content']);

        $newMarkdownContent = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $newMarkdownKey->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $newMarkdownContent->id,
            'order' => 1,
        ]);

        // Store old content ID for verification
        $oldContentId = $oldMarkdownContent->id;
        $oldTranslationKeyId = $oldMarkdownKey->id;

        // Publish the draft (this should update the existing post)
        $updatedPost = $this->conversionService->convertDraftToBlogPost($draft);

        // Verify the post is the same instance (updated, not new)
        $this->assertEquals($existingPost->id, $updatedPost->id);

        // Verify old content was cleaned up
        $this->assertNull(BlogContentMarkdown::find($oldContentId));
        $this->assertNull(TranslationKey::find($oldTranslationKeyId));

        // Verify new content exists and is independent
        $updatedContent = $updatedPost->contents->first();
        $this->assertNotEquals($newMarkdownContent->id, $updatedContent->content_id);

        $updatedMarkdownContent = $updatedContent->content;
        $this->assertEquals('New content', $updatedMarkdownContent->translationKey->translations->first()->text);
    }

    #[Test]
    public function create_draft_from_blog_post_with_game_review(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $prosKey = TranslationKey::factory()->create(['key' => 'original.pros']);
        $prosKey->translations()->create(['locale' => 'en', 'text' => 'Great gameplay']);

        $consKey = TranslationKey::factory()->create(['key' => 'original.cons']);
        $consKey->translations()->create(['locale' => 'en', 'text' => 'Bugs']);

        $gameReview = GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
            'game_title' => 'Test Game',
            'rating' => GameReviewRating::POSITIVE,
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
        ]);

        $linkLabelKey = TranslationKey::factory()->create(['key' => 'original.link']);
        $linkLabelKey->translations()->create(['locale' => 'en', 'text' => 'Steam Store']);

        GameReviewLink::factory()->create([
            'game_review_id' => $gameReview->id,
            'type' => 'store',
            'url' => 'https://store.steampowered.com/app/123',
            'label_translation_key_id' => $linkLabelKey->id,
            'order' => 1,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $this->assertNotNull($draft->gameReviewDraft);
        $gameReviewDraft = $draft->gameReviewDraft;

        $this->assertEquals('Test Game', $gameReviewDraft->game_title);
        $this->assertEquals(GameReviewRating::POSITIVE, $gameReviewDraft->rating);

        $this->assertNotEquals($prosKey->id, $gameReviewDraft->pros_translation_key_id);
        $this->assertNotEquals($consKey->id, $gameReviewDraft->cons_translation_key_id);

        $draftProsKey = $gameReviewDraft->prosTranslationKey;
        $draftConsKey = $gameReviewDraft->consTranslationKey;

        $this->assertEquals('Great gameplay', $draftProsKey->translations->first()->text);
        $this->assertEquals('Bugs', $draftConsKey->translations->first()->text);
        $this->assertStringContainsString('original.pros', $draftProsKey->key);
        $this->assertStringContainsString('draft', $draftProsKey->key);

        $this->assertCount(1, $gameReviewDraft->links);
        $draftLink = $gameReviewDraft->links->first();
        $this->assertEquals('store', $draftLink->type);
        $this->assertEquals('https://store.steampowered.com/app/123', $draftLink->url);
        $this->assertNotEquals($linkLabelKey->id, $draftLink->label_translation_key_id);

        $draftLinkLabelKey = $draftLink->labelTranslationKey;
        $this->assertEquals('Steam Store', $draftLinkLabelKey->translations->first()->text);
        $this->assertStringContainsString('original.link', $draftLinkLabelKey->key);
        $this->assertStringContainsString('draft', $draftLinkLabelKey->key);
    }

    #[Test]
    public function convert_draft_to_blog_post_with_game_review(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Draft Title']);

        $draft = BlogPostDraft::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
            'slug' => 'test-game-review',
            'type' => BlogPostType::GAME_REVIEW,
        ]);

        $prosKey = TranslationKey::factory()->create(['key' => 'draft.pros']);
        $prosKey->translations()->create(['locale' => 'en', 'text' => 'Amazing graphics']);

        $consKey = TranslationKey::factory()->create(['key' => 'draft.cons']);
        $consKey->translations()->create(['locale' => 'en', 'text' => 'Short campaign']);

        $gameReviewDraft = GameReviewDraft::create([
            'blog_post_draft_id' => $draft->id,
            'game_title' => 'Awesome Game',
            'rating' => GameReviewRating::POSITIVE,
            'pros_translation_key_id' => $prosKey->id,
            'cons_translation_key_id' => $consKey->id,
        ]);

        $linkLabelKey = TranslationKey::factory()->create(['key' => 'draft.link']);
        $linkLabelKey->translations()->create(['locale' => 'en', 'text' => 'Official Website']);

        GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReviewDraft->id,
            'type' => 'website',
            'url' => 'https://awesomegame.com',
            'label_translation_key_id' => $linkLabelKey->id,
            'order' => 1,
        ]);

        $publishedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertNotNull($publishedPost->gameReview);
        $gameReview = $publishedPost->gameReview;

        $this->assertEquals('Awesome Game', $gameReview->game_title);
        $this->assertEquals(GameReviewRating::POSITIVE, $gameReview->rating);
        $this->assertEquals($prosKey->id, $gameReview->pros_translation_key_id);
        $this->assertEquals($consKey->id, $gameReview->cons_translation_key_id);

        $this->assertCount(1, $gameReview->links);
        $publishedLink = $gameReview->links->first();
        $this->assertEquals('website', $publishedLink->type);
        $this->assertEquals('https://awesomegame.com', $publishedLink->url);
        $this->assertEquals($linkLabelKey->id, $publishedLink->label_translation_key_id);
    }

    #[Test]
    public function convert_draft_to_blog_post_removes_existing_game_review_when_draft_has_none(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'existing.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Existing Title']);

        $existingPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        GameReview::factory()->create([
            'blog_post_id' => $existingPost->id,
            'game_title' => 'Old Game',
        ]);

        $draftTitleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $draftTitleKey->translations()->create(['locale' => 'en', 'text' => 'Updated Title']);

        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $existingPost->id,
            'title_translation_key_id' => $draftTitleKey->id,
            'category_id' => $category->id,
            'slug' => 'updated-post',
            'type' => BlogPostType::ARTICLE,
        ]);

        $updatedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertEquals($existingPost->id, $updatedPost->id);
        $this->assertNull($updatedPost->gameReview);
    }

    #[Test]
    public function convert_draft_to_blog_post_updates_existing_game_review(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'existing.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Existing Title']);

        $existingPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $existingGameReview = GameReview::factory()->create([
            'blog_post_id' => $existingPost->id,
            'game_title' => 'Old Game',
            'rating' => GameReviewRating::POSITIVE,
        ]);

        $existingLinkId = $existingGameReview->links()->create([
            'type' => 'old',
            'url' => 'https://old.com',
            'label_translation_key_id' => TranslationKey::factory()->create(['key' => 'old.link'])->id,
            'order' => 1,
        ])->id;

        $draftTitleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $draftTitleKey->translations()->create(['locale' => 'en', 'text' => 'Updated Title']);

        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $existingPost->id,
            'title_translation_key_id' => $draftTitleKey->id,
            'category_id' => $category->id,
            'slug' => 'updated-game-review',
            'type' => BlogPostType::GAME_REVIEW,
        ]);

        $gameReviewDraft = GameReviewDraft::create([
            'blog_post_draft_id' => $draft->id,
            'game_title' => 'Updated Game',
            'rating' => GameReviewRating::POSITIVE,
        ]);

        $newLinkLabelKey = TranslationKey::factory()->create(['key' => 'new.link']);
        $newLinkLabelKey->translations()->create(['locale' => 'en', 'text' => 'New Link']);

        GameReviewDraftLink::factory()->create([
            'game_review_draft_id' => $gameReviewDraft->id,
            'type' => 'new',
            'url' => 'https://new.com',
            'label_translation_key_id' => $newLinkLabelKey->id,
            'order' => 1,
        ]);

        $updatedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertEquals($existingPost->id, $updatedPost->id);
        $this->assertNotNull($updatedPost->gameReview);

        $gameReview = $updatedPost->gameReview;
        $this->assertEquals($existingGameReview->id, $gameReview->id);
        $this->assertEquals('Updated Game', $gameReview->game_title);
        $this->assertEquals(GameReviewRating::POSITIVE, $gameReview->rating);

        $this->assertNull(GameReviewLink::find($existingLinkId));
        $this->assertCount(1, $gameReview->links);
        $newLink = $gameReview->links->first();
        $this->assertEquals('new', $newLink->type);
        $this->assertEquals('https://new.com', $newLink->url);
    }

    #[Test]
    public function convert_draft_to_blog_post_fails_validation_with_missing_title(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = new BlogPostDraft([
            'title_translation_key_id' => null,
            'category_id' => $category->id,
            'slug' => 'test-slug',
            'type' => BlogPostType::ARTICLE,
        ]);

        $this->expectException(ValidationException::class);
        $this->conversionService->convertDraftToBlogPost($draft);
    }

    #[Test]
    public function convert_draft_to_blog_post_fails_validation_with_missing_slug(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create();
        $draft = new BlogPostDraft([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
            'slug' => null,
            'type' => BlogPostType::ARTICLE,
        ]);

        $this->expectException(ValidationException::class);
        $this->conversionService->convertDraftToBlogPost($draft);
    }

    #[Test]
    public function convert_draft_to_blog_post_fails_validation_with_missing_type(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create();
        $draft = new BlogPostDraft([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
            'slug' => 'test-slug',
            'type' => null,
        ]);

        $this->expectException(ValidationException::class);
        $this->conversionService->convertDraftToBlogPost($draft);
    }

    #[Test]
    public function convert_draft_to_blog_post_fails_validation_with_missing_category(): void
    {
        $titleKey = TranslationKey::factory()->create();
        $draft = new BlogPostDraft([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => null,
            'slug' => 'test-slug',
            'type' => BlogPostType::ARTICLE,
        ]);

        $this->expectException(ValidationException::class);
        $this->conversionService->convertDraftToBlogPost($draft);
    }

    #[Test]
    public function convert_draft_to_blog_post_fails_validation_with_non_existent_title_translation_key(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = new BlogPostDraft([
            'title_translation_key_id' => 99999,
            'category_id' => $category->id,
            'slug' => 'test-slug',
            'type' => BlogPostType::ARTICLE,
        ]);

        $this->expectException(ValidationException::class);
        $this->conversionService->convertDraftToBlogPost($draft);
    }

    #[Test]
    public function convert_draft_to_blog_post_fails_validation_with_non_existent_category(): void
    {
        $titleKey = TranslationKey::factory()->create();
        $draft = new BlogPostDraft([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => 99999,
            'slug' => 'test-slug',
            'type' => BlogPostType::ARTICLE,
        ]);

        $this->expectException(ValidationException::class);
        $this->conversionService->convertDraftToBlogPost($draft);
    }

    #[Test]
    public function create_draft_from_blog_post_with_gallery_content(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $picture = Picture::factory()->create();

        $galleryContent = BlogContentGallery::factory()->create();

        $captionKey = TranslationKey::factory()->create(['key' => 'original.caption']);
        $captionKey->translations()->create(['locale' => 'en', 'text' => 'Original caption']);

        $galleryContent->pictures()->attach($picture->id, [
            'order' => 1,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $galleryContent->id,
            'order' => 1,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $this->assertCount(1, $draft->contents);
        $draftContent = $draft->contents->first();
        $this->assertEquals(BlogContentGallery::class, $draftContent->content_type);
        $this->assertNotEquals($galleryContent->id, $draftContent->content_id);

        $draftGallery = $draftContent->content;
        $this->assertInstanceOf(BlogContentGallery::class, $draftGallery);
        $this->assertNotEquals($galleryContent->id, $draftGallery->id);

        $this->assertCount(1, $draftGallery->pictures);
        $draftPicture = $draftGallery->pictures->first();
        $this->assertEquals($picture->id, $draftPicture->id);

        $this->assertNotEquals($captionKey->id, $draftPicture->pivot->caption_translation_key_id);

        $draftCaptionKey = TranslationKey::find($draftPicture->pivot->caption_translation_key_id);
        $this->assertEquals('Original caption', $draftCaptionKey->translations->first()->text);
        $this->assertStringContainsString('original.caption', $draftCaptionKey->key);
        $this->assertStringContainsString('copy', $draftCaptionKey->key);
    }

    #[Test]
    public function create_draft_from_blog_post_with_video_content(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $video = Video::factory()->create();

        $captionKey = TranslationKey::factory()->create(['key' => 'original.video.caption']);
        $captionKey->translations()->create(['locale' => 'en', 'text' => 'Original video caption']);

        $videoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $this->assertCount(1, $draft->contents);
        $draftContent = $draft->contents->first();
        $this->assertEquals(BlogContentVideo::class, $draftContent->content_type);
        $this->assertNotEquals($videoContent->id, $draftContent->content_id);

        $draftVideo = $draftContent->content;
        $this->assertInstanceOf(BlogContentVideo::class, $draftVideo);
        $this->assertNotEquals($videoContent->id, $draftVideo->id);
        $this->assertEquals($video->id, $draftVideo->video_id);

        $this->assertNotEquals($captionKey->id, $draftVideo->caption_translation_key_id);

        $draftCaptionKey = $draftVideo->captionTranslationKey;
        $this->assertEquals('Original video caption', $draftCaptionKey->translations->first()->text);
        $this->assertStringContainsString('original.video.caption', $draftCaptionKey->key);
        $this->assertStringContainsString('copy', $draftCaptionKey->key);
    }

    #[Test]
    public function convert_draft_to_blog_post_cleans_up_gallery_content_properly(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'existing.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Existing Title']);

        $existingPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $picture = Picture::factory()->create();
        $oldCaptionKey = TranslationKey::factory()->create(['key' => 'old.caption']);
        $oldCaptionKey->translations()->create(['locale' => 'en', 'text' => 'Old caption']);

        $oldGallery = BlogContentGallery::factory()->create();
        $oldGallery->pictures()->attach($picture->id, [
            'order' => 1,
            'caption_translation_key_id' => $oldCaptionKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $existingPost->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $oldGallery->id,
            'order' => 1,
        ]);

        $draftTitleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $draftTitleKey->translations()->create(['locale' => 'en', 'text' => 'Updated Title']);

        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $existingPost->id,
            'title_translation_key_id' => $draftTitleKey->id,
            'category_id' => $category->id,
            'slug' => 'updated-post',
            'type' => BlogPostType::ARTICLE,
        ]);

        $newCaptionKey = TranslationKey::factory()->create(['key' => 'new.caption']);
        $newCaptionKey->translations()->create(['locale' => 'en', 'text' => 'New caption']);

        $newGallery = BlogContentGallery::factory()->create();
        $newGallery->pictures()->attach($picture->id, [
            'order' => 1,
            'caption_translation_key_id' => $newCaptionKey->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $newGallery->id,
            'order' => 1,
        ]);

        $oldGalleryId = $oldGallery->id;
        $oldCaptionKeyId = $oldCaptionKey->id;

        $updatedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertNull(BlogContentGallery::find($oldGalleryId));
        $this->assertNull(TranslationKey::find($oldCaptionKeyId));

        $this->assertCount(1, $updatedPost->contents);
        $updatedContent = $updatedPost->contents->first();
        $this->assertEquals(BlogContentGallery::class, $updatedContent->content_type);
        $this->assertNotEquals($newGallery->id, $updatedContent->content_id);

        $updatedGallery = $updatedContent->content;
        $this->assertCount(1, $updatedGallery->pictures);
        $updatedPicture = $updatedGallery->pictures->first();
        $this->assertEquals($picture->id, $updatedPicture->id);
        $this->assertEquals('New caption', TranslationKey::find($updatedPicture->pivot->caption_translation_key_id)->translations->first()->text);
    }

    #[Test]
    public function convert_draft_to_blog_post_cleans_up_video_content_properly(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'existing.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Existing Title']);

        $existingPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $video = Video::factory()->create();
        $oldCaptionKey = TranslationKey::factory()->create(['key' => 'old.video.caption']);
        $oldCaptionKey->translations()->create(['locale' => 'en', 'text' => 'Old video caption']);

        $oldVideoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $oldCaptionKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $existingPost->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $oldVideoContent->id,
            'order' => 1,
        ]);

        $draftTitleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $draftTitleKey->translations()->create(['locale' => 'en', 'text' => 'Updated Title']);

        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $existingPost->id,
            'title_translation_key_id' => $draftTitleKey->id,
            'category_id' => $category->id,
            'slug' => 'updated-post',
            'type' => BlogPostType::ARTICLE,
        ]);

        $newCaptionKey = TranslationKey::factory()->create(['key' => 'new.video.caption']);
        $newCaptionKey->translations()->create(['locale' => 'en', 'text' => 'New video caption']);

        $newVideoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $newCaptionKey->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $newVideoContent->id,
            'order' => 1,
        ]);

        $oldVideoContentId = $oldVideoContent->id;
        $oldCaptionKeyId = $oldCaptionKey->id;

        $updatedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertNull(BlogContentVideo::find($oldVideoContentId));
        $this->assertNull(TranslationKey::find($oldCaptionKeyId));

        $this->assertCount(1, $updatedPost->contents);
        $updatedContent = $updatedPost->contents->first();
        $this->assertEquals(BlogContentVideo::class, $updatedContent->content_type);
        $this->assertNotEquals($newVideoContent->id, $updatedContent->content_id);

        $updatedVideoContent = $updatedContent->content;
        $this->assertEquals($video->id, $updatedVideoContent->video_id);
        $this->assertEquals('New video caption', $updatedVideoContent->captionTranslationKey->translations->first()->text);
    }

    #[Test]
    public function convert_draft_to_blog_post_with_mixed_content_types(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Draft Title']);

        $draft = BlogPostDraft::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
            'slug' => 'mixed-content',
            'type' => BlogPostType::ARTICLE,
        ]);

        $markdownKey = TranslationKey::factory()->create(['key' => 'draft.markdown']);
        $markdownKey->translations()->create(['locale' => 'en', 'text' => 'Draft markdown content']);

        $markdownContent = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        $gallery = BlogContentGallery::factory()->create();
        $picture = Picture::factory()->create();

        $galleryCaptionKey = TranslationKey::factory()->create(['key' => 'draft.gallery.caption']);
        $galleryCaptionKey->translations()->create(['locale' => 'en', 'text' => 'Gallery caption']);

        $gallery->pictures()->attach($picture->id, [
            'order' => 1,
            'caption_translation_key_id' => $galleryCaptionKey->id,
        ]);

        $video = Video::factory()->create();
        $videoCaptionKey = TranslationKey::factory()->create(['key' => 'draft.video.caption']);
        $videoCaptionKey->translations()->create(['locale' => 'en', 'text' => 'Video caption']);

        $videoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $videoCaptionKey->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdownContent->id,
            'order' => 1,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 2,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 3,
        ]);

        $publishedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertCount(3, $publishedPost->contents);

        $publishedContents = $publishedPost->contents->sortBy('order');

        $markdownPublished = $publishedContents->first();
        $this->assertEquals(BlogContentMarkdown::class, $markdownPublished->content_type);
        $this->assertNotEquals($markdownContent->id, $markdownPublished->content_id);
        $this->assertEquals('Draft markdown content', $markdownPublished->content->translationKey->translations->first()->text);

        $galleryPublished = $publishedContents->skip(1)->first();
        $this->assertEquals(BlogContentGallery::class, $galleryPublished->content_type);
        $this->assertNotEquals($gallery->id, $galleryPublished->content_id);
        $this->assertCount(1, $galleryPublished->content->pictures);

        $videoPublished = $publishedContents->skip(2)->first();
        $this->assertEquals(BlogContentVideo::class, $videoPublished->content_type);
        $this->assertNotEquals($videoContent->id, $videoPublished->content_id);
        $this->assertEquals($video->id, $videoPublished->content->video_id);
        $this->assertEquals('Video caption', $videoPublished->content->captionTranslationKey->translations->first()->text);
    }

    #[Test]
    public function generate_unique_translation_key_handles_collisions(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'collision.test']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        TranslationKey::factory()->create(['key' => 'collision.test_draft']);
        TranslationKey::factory()->create(['key' => 'collision.test_draft_1']);
        TranslationKey::factory()->create(['key' => 'collision.test_draft_2']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $draftTitleKey = $draft->titleTranslationKey;
        $this->assertNotEquals($titleKey->key, $draftTitleKey->key);
        $this->assertStringContainsString('collision.test', $draftTitleKey->key);
        $this->assertStringContainsString('draft', $draftTitleKey->key);

        $this->assertEquals('collision.test_draft_3', $draftTitleKey->key);
    }

    #[Test]
    public function duplicate_translation_key_with_multiple_locales(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'multilang.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'English Title']);
        $titleKey->translations()->create(['locale' => 'fr', 'text' => 'Titre Français']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $draftTitleKey = $draft->titleTranslationKey;
        $this->assertNotEquals($titleKey->id, $draftTitleKey->id);
        $this->assertCount(2, $draftTitleKey->translations);

        $englishTranslation = $draftTitleKey->translations->firstWhere('locale', 'en');
        $frenchTranslation = $draftTitleKey->translations->firstWhere('locale', 'fr');

        $this->assertEquals('English Title', $englishTranslation->text);
        $this->assertEquals('Titre Français', $frenchTranslation->text);
    }

    #[Test]
    public function create_draft_from_blog_post_with_game_review_without_translation_keys(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $gameReview = GameReview::factory()->create([
            'blog_post_id' => $blogPost->id,
            'game_title' => 'Test Game',
            'rating' => GameReviewRating::POSITIVE,
            'pros_translation_key_id' => null,
            'cons_translation_key_id' => null,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $this->assertNotNull($draft->gameReviewDraft);
        $gameReviewDraft = $draft->gameReviewDraft;

        $this->assertEquals('Test Game', $gameReviewDraft->game_title);
        $this->assertEquals(GameReviewRating::POSITIVE, $gameReviewDraft->rating);
        $this->assertNull($gameReviewDraft->pros_translation_key_id);
        $this->assertNull($gameReviewDraft->cons_translation_key_id);
    }

    #[Test]
    public function create_draft_from_blog_post_without_content(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'empty.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Empty Post']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $this->assertEquals($blogPost->id, $draft->original_blog_post_id);
        $this->assertEquals($blogPost->slug, $draft->slug);
        $this->assertEquals($blogPost->type, $draft->type);
        $this->assertEquals($blogPost->category_id, $draft->category_id);
        $this->assertEquals($blogPost->cover_picture_id, $draft->cover_picture_id);

        $this->assertNotEquals($titleKey->id, $draft->title_translation_key_id);
        $draftTitleKey = $draft->titleTranslationKey;
        $this->assertEquals('Empty Post', $draftTitleKey->translations->first()->text);
        $this->assertStringContainsString('empty.title', $draftTitleKey->key);
        $this->assertStringContainsString('draft', $draftTitleKey->key);

        $this->assertCount(0, $draft->contents);
        $this->assertNull($draft->gameReviewDraft);
    }

    #[Test]
    public function convert_draft_to_blog_post_without_game_review_draft(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Draft Title']);

        $draft = BlogPostDraft::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
            'slug' => 'simple-post',
            'type' => BlogPostType::ARTICLE,
        ]);

        $publishedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertEquals('simple-post', $publishedPost->slug);
        $this->assertEquals(BlogPostType::ARTICLE, $publishedPost->type);
        $this->assertEquals($category->id, $publishedPost->category_id);
        $this->assertEquals($titleKey->id, $publishedPost->title_translation_key_id);
        $this->assertNull($publishedPost->gameReview);
        $this->assertCount(0, $publishedPost->contents);

        $draft->refresh();
        $this->assertEquals($publishedPost->id, $draft->original_blog_post_id);
    }

    #[Test]
    public function create_draft_from_blog_post_with_video_content_without_caption(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $video = Video::factory()->create();
        $videoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => null,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $videoContent->id,
            'order' => 1,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $this->assertCount(1, $draft->contents);
        $draftContent = $draft->contents->first();
        $draftVideo = $draftContent->content;

        $this->assertInstanceOf(BlogContentVideo::class, $draftVideo);
        $this->assertEquals($video->id, $draftVideo->video_id);
        $this->assertNull($draftVideo->caption_translation_key_id);
    }

    #[Test]
    public function convert_draft_to_blog_post_cleans_up_video_content_without_caption(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'existing.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Existing Title']);

        $existingPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $video = Video::factory()->create();
        $oldVideoContent = BlogContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => null,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $existingPost->id,
            'content_type' => BlogContentVideo::class,
            'content_id' => $oldVideoContent->id,
            'order' => 1,
        ]);

        $draftTitleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $draftTitleKey->translations()->create(['locale' => 'en', 'text' => 'Updated Title']);

        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $existingPost->id,
            'title_translation_key_id' => $draftTitleKey->id,
            'category_id' => $category->id,
            'slug' => 'updated-post',
            'type' => BlogPostType::ARTICLE,
        ]);

        $oldVideoContentId = $oldVideoContent->id;

        $updatedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertNull(BlogContentVideo::find($oldVideoContentId));
        $this->assertCount(0, $updatedPost->contents);
    }

    #[Test]
    public function create_draft_from_blog_post_with_gallery_without_captions(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'original.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Original Title']);

        $blogPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $picture1 = Picture::factory()->create();
        $picture2 = Picture::factory()->create();

        $galleryContent = BlogContentGallery::factory()->create();

        $galleryContent->pictures()->attach($picture1->id, ['order' => 1, 'caption_translation_key_id' => null]);
        $galleryContent->pictures()->attach($picture2->id, ['order' => 2, 'caption_translation_key_id' => null]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $blogPost->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $galleryContent->id,
            'order' => 1,
        ]);

        $draft = $this->conversionService->createDraftFromBlogPost($blogPost);

        $this->assertCount(1, $draft->contents);
        $draftContent = $draft->contents->first();
        $draftGallery = $draftContent->content;

        $this->assertInstanceOf(BlogContentGallery::class, $draftGallery);
        $this->assertCount(2, $draftGallery->pictures);

        foreach ($draftGallery->pictures as $picture) {
            $this->assertNull($picture->pivot->caption_translation_key_id);
        }
    }

    #[Test]
    public function convert_draft_to_blog_post_handles_empty_existing_content(): void
    {
        $category = BlogCategory::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'existing.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Existing Title']);

        $existingPost = BlogPost::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
        ]);

        $draftTitleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $draftTitleKey->translations()->create(['locale' => 'en', 'text' => 'Updated Title']);

        $draft = BlogPostDraft::factory()->create([
            'original_blog_post_id' => $existingPost->id,
            'title_translation_key_id' => $draftTitleKey->id,
            'category_id' => $category->id,
            'slug' => 'updated-post',
            'type' => BlogPostType::ARTICLE,
        ]);

        $updatedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertEquals($existingPost->id, $updatedPost->id);
        $this->assertCount(0, $updatedPost->contents);
        $this->assertEquals('Updated Title', $updatedPost->titleTranslationKey->translations->first()->text);
    }

    #[Test]
    public function map_draft_attributes_copies_all_required_fields(): void
    {
        $category = BlogCategory::factory()->create();
        $picture = Picture::factory()->create();
        $titleKey = TranslationKey::factory()->create(['key' => 'draft.title']);
        $titleKey->translations()->create(['locale' => 'en', 'text' => 'Draft Title']);

        $draft = BlogPostDraft::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'category_id' => $category->id,
            'cover_picture_id' => $picture->id,
            'slug' => 'test-mapping',
            'type' => BlogPostType::GAME_REVIEW,
        ]);

        $publishedPost = $this->conversionService->convertDraftToBlogPost($draft);

        $this->assertEquals('test-mapping', $publishedPost->slug);
        $this->assertEquals($titleKey->id, $publishedPost->title_translation_key_id);
        $this->assertEquals(BlogPostType::GAME_REVIEW, $publishedPost->type);
        $this->assertEquals($category->id, $publishedPost->category_id);
        $this->assertEquals($picture->id, $publishedPost->cover_picture_id);
    }
}
