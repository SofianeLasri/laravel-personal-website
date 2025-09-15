<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostType;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostEditPageController extends Controller
{
    public function editPage(Request $request): Response
    {
        $draftId = $request->query('draft-id');
        $blogPostId = $request->query('blog-post-id');

        $draft = null;

        if ($draftId) {
            // Editing an existing draft
            $draft = BlogPostDraft::with([
                'titleTranslationKey.translations',
                'category',
                'coverPicture.optimizedPictures',
                'contents.content',
                'originalBlogPost',
                'gameReviewDraft',
            ])->findOrFail($draftId);
        } elseif ($blogPostId) {
            // Creating a draft from an existing blog post
            $blogPost = BlogPost::with([
                'titleTranslationKey.translations',
                'category',
                'coverPicture.optimizedPictures',
                'contents.content',
                'gameReview',
            ])->findOrFail($blogPostId);

            // Check if a draft already exists for this blog post
            $draft = BlogPostDraft::where('original_blog_post_id', $blogPostId)->first();

            if (! $draft) {
                // Create a new draft from the blog post
                $draft = $this->createDraftFromBlogPost($blogPost);
            } else {
                // Load relationships for existing draft
                $draft->load([
                    'titleTranslationKey.translations',
                    'category',
                    'coverPicture.optimizedPictures',
                    'contents.content',
                    'originalBlogPost',
                    'gameReviewDraft',
                ]);
            }
        } else {
            // Creating a new draft from scratch
            $draft = $this->createNewDraft();
        }

        // Get all categories for the select dropdown
        $categories = BlogCategory::with('nameTranslationKey.translations')
            ->orderBy('order')
            ->get();

        // Get all pictures and videos for content selection
        $pictures = Picture::with('optimizedPictures')
            ->orderBy('created_at', 'desc')
            ->get();

        $videos = Video::orderBy('created_at', 'desc')->get();

        return Inertia::render('dashboard/blog-posts/EditPage', [
            'blogPostDraft' => $draft,
            'categories' => $categories,
            'pictures' => $pictures,
            'videos' => $videos,
            'blogPostTypes' => BlogPostType::cases(),
        ]);
    }

    private function createDraftFromBlogPost(BlogPost $blogPost): BlogPostDraft
    {
        // Create the draft
        $draft = BlogPostDraft::create([
            'original_blog_post_id' => $blogPost->id,
            'slug' => $blogPost->slug,
            'title_translation_key_id' => $blogPost->title_translation_key_id,
            'type' => $blogPost->type,
            'category_id' => $blogPost->category_id,
            'cover_picture_id' => $blogPost->cover_picture_id,
            'published_at' => $blogPost->published_at,
        ]);

        // Copy contents
        foreach ($blogPost->contents as $content) {
            $draft->contents()->create([
                'content_type' => $content->content_type,
                'content_id' => $content->content_id,
                'order' => $content->order,
            ]);
        }

        // Copy game review if exists
        if ($blogPost->gameReview) {
            $gameReviewDraft = $draft->gameReviewDraft()->create([
                'game_title' => $blogPost->gameReview->game_title,
                'release_date' => $blogPost->gameReview->release_date,
                'genre' => $blogPost->gameReview->genre,
                'developer' => $blogPost->gameReview->developer,
                'publisher' => $blogPost->gameReview->publisher,
                'platforms' => $blogPost->gameReview->platforms,
                'cover_picture_id' => $blogPost->gameReview->cover_picture_id,
                'pros_translation_key_id' => $blogPost->gameReview->pros_translation_key_id,
                'cons_translation_key_id' => $blogPost->gameReview->cons_translation_key_id,
                'score' => $blogPost->gameReview->score,
            ]);

            // Copy game review links
            foreach ($blogPost->gameReview->links as $link) {
                $gameReviewDraft->links()->create([
                    'type' => $link->type,
                    'url' => $link->url,
                    'label_translation_key_id' => $link->label_translation_key_id,
                    'order' => $link->order,
                ]);
            }
        }

        // Load relationships
        $draft->load([
            'titleTranslationKey.translations',
            'category',
            'coverPicture.optimizedPictures',
            'contents.content',
            'originalBlogPost',
            'gameReviewDraft',
        ]);

        return $draft;
    }

    private function createNewDraft(): BlogPostDraft
    {
        // Create translation key for title
        $titleTranslationKey = TranslationKey::create([
            'key' => 'blog_post_draft_title_'.uniqid(),
        ]);

        // Create empty translations
        $titleTranslationKey->translations()->createMany([
            ['locale' => 'fr', 'text' => ''],
            ['locale' => 'en', 'text' => ''],
        ]);

        // Get first category or create a default one if none exists
        $category = BlogCategory::first();
        if (! $category) {
            $categoryNameKey = TranslationKey::create([
                'key' => 'blog_category_default',
            ]);

            $categoryNameKey->translations()->createMany([
                ['locale' => 'fr', 'text' => 'Non catégorisé'],
                ['locale' => 'en', 'text' => 'Uncategorized'],
            ]);

            $category = BlogCategory::create([
                'slug' => 'uncategorized',
                'name_translation_key_id' => $categoryNameKey->id,
                'color' => '#6B7280',
                'order' => 0,
            ]);
        }

        // Create the draft
        $draft = BlogPostDraft::create([
            'slug' => 'new-article-'.uniqid(),
            'title_translation_key_id' => $titleTranslationKey->id,
            'type' => BlogPostType::ARTICLE,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        // Load relationships
        $draft->load([
            'titleTranslationKey.translations',
            'category',
            'coverPicture.optimizedPictures',
            'contents.content',
            'originalBlogPost',
            'gameReviewDraft',
        ]);

        return $draft;
    }
}
