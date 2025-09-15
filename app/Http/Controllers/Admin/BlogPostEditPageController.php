<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostType;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Models\Picture;
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
            // Creating a new draft from scratch - will be created on first save
            $draft = null;
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
            'blogPostTypes' => array_map(fn (BlogPostType $type) => [
                'name' => $type->name,
                'value' => $type->value,
            ], BlogPostType::cases()),
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
}
