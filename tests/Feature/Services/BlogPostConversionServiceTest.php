<?php

namespace Tests\Feature\Services;

use App\Models\BlogCategory;
use App\Models\BlogContentMarkdown;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\TranslationKey;
use App\Services\BlogPostConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
