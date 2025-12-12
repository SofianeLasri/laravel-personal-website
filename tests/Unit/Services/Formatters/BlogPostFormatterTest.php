<?php

namespace Tests\Unit\Services\Formatters;

use App\Enums\BlogPostType;
use App\Enums\CategoryColor;
use App\Enums\GameReviewRating;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\ContentMarkdown;
use App\Models\GameReview;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Services\Formatters\BlogPostFormatter;
use App\Services\Formatters\ContentBlockFormatter;
use App\Services\Formatters\MediaFormatter;
use App\Services\Formatters\TranslationHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BlogPostFormatter::class)]
class BlogPostFormatterTest extends TestCase
{
    private BlogPostFormatter $formatter;

    private MediaFormatter&MockInterface $mediaFormatter;

    private TranslationHelper&MockInterface $translationHelper;

    private ContentBlockFormatter&MockInterface $contentBlockFormatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaFormatter = Mockery::mock(MediaFormatter::class);
        $this->translationHelper = Mockery::mock(TranslationHelper::class);
        $this->contentBlockFormatter = Mockery::mock(ContentBlockFormatter::class);

        $this->formatter = new BlogPostFormatter(
            $this->mediaFormatter,
            $this->translationHelper,
            $this->contentBlockFormatter,
        );
    }

    #[Test]
    public function it_formats_blog_post_short(): void
    {
        $blogPost = $this->createMockBlogPost();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->twice()
            ->andReturn('Blog Post Title', 'Category Name');

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2024');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'cover.jpg']);

        $result = $this->formatter->formatShort($blogPost);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Blog Post Title', $result['title']);
        $this->assertEquals('test-slug', $result['slug']);
        $this->assertEquals(BlogPostType::ARTICLE, $result['type']);
        $this->assertEquals('Category Name', $result['category']['name']);
        $this->assertEquals(CategoryColor::BLUE, $result['category']['color']);
        $this->assertEquals(['filename' => 'cover.jpg'], $result['coverImage']);
        $this->assertEquals('Janvier 2024', $result['publishedAtFormatted']);
    }

    #[Test]
    public function it_formats_blog_post_hero_with_longer_excerpt(): void
    {
        $blogPost = $this->createMockBlogPost();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Title', 'Category');

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Février 2024');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'cover.jpg']);

        $result = $this->formatter->formatHero($blogPost);

        $this->assertArrayHasKey('excerpt', $result);
        $this->assertArrayHasKey('coverImage', $result);
    }

    #[Test]
    public function it_formats_blog_post_full_with_contents(): void
    {
        $blogPost = $this->createMockBlogPost();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Title', 'Category');

        $this->translationHelper
            ->shouldReceive('getLocale')
            ->andReturn('fr');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'cover.jpg']);

        $this->contentBlockFormatter
            ->shouldReceive('format')
            ->andReturn([
                'id' => 1,
                'order' => 0,
                'content_type' => ContentMarkdown::class,
                'markdown' => '# Hello',
            ]);

        $result = $this->formatter->formatFull($blogPost);

        $this->assertArrayHasKey('contents', $result);
        $this->assertArrayHasKey('publishedAtFormatted', $result);
        $this->assertArrayNotHasKey('gameReview', $result);
    }

    #[Test]
    public function it_formats_game_review_post_with_review_data(): void
    {
        $blogPost = $this->createMockBlogPostWithGameReview();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Title', 'Category', 'Pros text', 'Cons text');

        $this->translationHelper
            ->shouldReceive('getLocale')
            ->andReturn('fr');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'cover.jpg']);

        $this->contentBlockFormatter
            ->shouldReceive('format')
            ->andReturn([
                'id' => 1,
                'order' => 0,
                'content_type' => ContentMarkdown::class,
                'markdown' => '# Review',
            ]);

        $result = $this->formatter->formatFull($blogPost);

        $this->assertArrayHasKey('gameReview', $result);
        $this->assertEquals('Test Game', $result['gameReview']['gameTitle']);
        $this->assertEquals(GameReviewRating::POSITIVE, $result['gameReview']['rating']);
    }

    #[Test]
    public function it_formats_game_review(): void
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $prosKey */
        $prosKey = Mockery::mock(TranslationKey::class)->makePartial();
        $prosKey->translations = $translations;

        /** @var TranslationKey&MockInterface $consKey */
        $consKey = Mockery::mock(TranslationKey::class)->makePartial();
        $consKey->translations = $translations;

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();

        /** @var GameReview&MockInterface $gameReview */
        $gameReview = Mockery::mock(GameReview::class)->makePartial();
        $gameReview->game_title = 'Elden Ring';
        $gameReview->release_date = Carbon::create(2022, 2, 25);
        $gameReview->genre = 'Action RPG';
        $gameReview->developer = 'FromSoftware';
        $gameReview->publisher = 'Bandai Namco';
        $gameReview->platforms = ['PC', 'PS5', 'Xbox Series X'];
        $gameReview->rating = GameReviewRating::POSITIVE;
        $gameReview->prosTranslationKey = $prosKey;
        $gameReview->consTranslationKey = $consKey;
        $gameReview->coverPicture = $coverPicture;

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->twice()
            ->andReturn('Amazing gameplay and world', 'Sometimes frustrating difficulty');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->with($coverPicture)
            ->andReturn(['filename' => 'elden-ring.jpg']);

        $result = $this->formatter->formatGameReview($gameReview);

        $this->assertEquals('Elden Ring', $result['gameTitle']);
        $this->assertEquals('Action RPG', $result['genre']);
        $this->assertEquals('FromSoftware', $result['developer']);
        $this->assertEquals(GameReviewRating::POSITIVE, $result['rating']);
        $this->assertEquals('Amazing gameplay and world', $result['pros']);
        $this->assertEquals('Sometimes frustrating difficulty', $result['cons']);
        $this->assertEquals(['filename' => 'elden-ring.jpg'], $result['coverPicture']);
    }

    #[Test]
    public function it_extracts_excerpt_from_first_text_block(): void
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $translationKey */
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->translations = $translations;

        /** @var ContentMarkdown&MockInterface $contentMarkdown */
        $contentMarkdown = Mockery::mock(ContentMarkdown::class)->makePartial();
        $contentMarkdown->translationKey = $translationKey;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->content_type = ContentMarkdown::class;
        $content->order = 0;
        $content->content = $contentMarkdown;

        $contents = new Collection([$content]);

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->with($translations)
            ->andReturn('This is a test markdown content that should be truncated.');

        $result = $this->formatter->extractExcerptFromFirstTextBlock($contents, 30);

        $this->assertStringContainsString('This is a test', $result);
        $this->assertStringEndsWith('...', $result);
    }

    #[Test]
    public function it_returns_empty_excerpt_when_no_markdown_content(): void
    {
        $contents = new Collection;

        $result = $this->formatter->extractExcerptFromFirstTextBlock($contents);

        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_formats_draft_full_with_preview_flag(): void
    {
        $draft = $this->createMockBlogPostDraft();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Draft Title', 'Category');

        $this->translationHelper
            ->shouldReceive('getLocale')
            ->andReturn('fr');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'draft-cover.jpg']);

        $this->contentBlockFormatter
            ->shouldReceive('format')
            ->andReturn([
                'id' => 1,
                'order' => 0,
                'content_type' => ContentMarkdown::class,
                'markdown' => '# Draft content',
            ]);

        $result = $this->formatter->formatDraftFull($draft);

        $this->assertTrue($result['isPreview']);
        $this->assertEquals('Draft Title', $result['title']);
        $this->assertArrayHasKey('contents', $result);
    }

    /**
     * Create a mock BlogPost for testing.
     */
    private function createMockBlogPost(): BlogPost&MockInterface
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $titleKey */
        $titleKey = Mockery::mock(TranslationKey::class)->makePartial();
        $titleKey->translations = $translations;

        /** @var TranslationKey&MockInterface $categoryNameKey */
        $categoryNameKey = Mockery::mock(TranslationKey::class)->makePartial();
        $categoryNameKey->translations = $translations;

        /** @var BlogCategory&MockInterface $category */
        $category = Mockery::mock(BlogCategory::class)->makePartial();
        $category->nameTranslationKey = $categoryNameKey;
        $category->color = CategoryColor::BLUE;

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();

        /** @var BlogPost&MockInterface $blogPost */
        $blogPost = Mockery::mock(BlogPost::class)->makePartial();
        $blogPost->id = 1;
        $blogPost->slug = 'test-slug';
        $blogPost->type = BlogPostType::ARTICLE;
        $blogPost->titleTranslationKey = $titleKey;
        $blogPost->category = $category;
        $blogPost->coverPicture = $coverPicture;
        $blogPost->created_at = Carbon::create(2024, 1, 15);
        $blogPost->contents = new Collection;
        $blogPost->gameReview = null;

        return $blogPost;
    }

    /**
     * Create a mock BlogPost with game review for testing.
     */
    private function createMockBlogPostWithGameReview(): BlogPost&MockInterface
    {
        $blogPost = $this->createMockBlogPost();
        $blogPost->type = BlogPostType::GAME_REVIEW;

        $translations = new Collection;

        /** @var TranslationKey&MockInterface $prosKey */
        $prosKey = Mockery::mock(TranslationKey::class)->makePartial();
        $prosKey->translations = $translations;

        /** @var TranslationKey&MockInterface $consKey */
        $consKey = Mockery::mock(TranslationKey::class)->makePartial();
        $consKey->translations = $translations;

        /** @var Picture&MockInterface $gameCover */
        $gameCover = Mockery::mock(Picture::class)->makePartial();

        /** @var GameReview&MockInterface $gameReview */
        $gameReview = Mockery::mock(GameReview::class)->makePartial();
        $gameReview->game_title = 'Test Game';
        $gameReview->rating = GameReviewRating::POSITIVE;
        $gameReview->prosTranslationKey = $prosKey;
        $gameReview->consTranslationKey = $consKey;
        $gameReview->coverPicture = $gameCover;

        $blogPost->gameReview = $gameReview;

        return $blogPost;
    }

    /**
     * Create a mock BlogPostDraft for testing.
     */
    private function createMockBlogPostDraft(): BlogPostDraft&MockInterface
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $titleKey */
        $titleKey = Mockery::mock(TranslationKey::class)->makePartial();
        $titleKey->translations = $translations;

        /** @var TranslationKey&MockInterface $categoryNameKey */
        $categoryNameKey = Mockery::mock(TranslationKey::class)->makePartial();
        $categoryNameKey->translations = $translations;

        /** @var BlogCategory&MockInterface $category */
        $category = Mockery::mock(BlogCategory::class)->makePartial();
        $category->nameTranslationKey = $categoryNameKey;
        $category->color = CategoryColor::GREEN;

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();

        /** @var BlogPostDraft&MockInterface $draft */
        $draft = Mockery::mock(BlogPostDraft::class)->makePartial();
        $draft->id = 1;
        $draft->slug = 'draft-slug';
        $draft->type = BlogPostType::ARTICLE;
        $draft->titleTranslationKey = $titleKey;
        $draft->category = $category;
        $draft->coverPicture = $coverPicture;
        $draft->created_at = Carbon::create(2024, 2, 20);
        $draft->contents = new Collection;
        $draft->gameReviewDraft = null;

        return $draft;
    }
}
