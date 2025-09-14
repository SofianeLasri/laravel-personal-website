<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlogPostController extends Controller
{
    public function __construct(private readonly BlogService $blogService) {}

    /**
     * Display a paginated listing of drafts and posts
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $type = $request->get('type');
        $categoryId = $request->get('category_id');
        $status = $request->get('status', 'all'); // all, drafts, published

        $drafts = [];
        $posts = [];

        if ($status === 'all' || $status === 'drafts') {
            $draftsQuery = BlogPostDraft::query()
                ->with(['category', 'coverPicture'])
                ->orderBy('updated_at', 'desc');

            if ($type) {
                $draftsQuery->where('type', $type);
            }

            if ($categoryId) {
                $draftsQuery->where('category_id', $categoryId);
            }

            $drafts = $draftsQuery->paginate($perPage)->toArray();
            $drafts['data'] = array_map(function ($draft) {
                return [
                    'id' => $draft['id'],
                    'slug' => $draft['slug'],
                    'type' => $draft['type'],
                    'status' => 'draft',
                    'category' => $draft['category'],
                    'cover_picture' => $draft['cover_picture'],
                    'blog_post_id' => $draft['blog_post_id'],
                    'created_at' => $draft['created_at'],
                    'updated_at' => $draft['updated_at'],
                ];
            }, $drafts['data']);
        }

        if ($status === 'all' || $status === 'published') {
            $postsQuery = BlogPost::query()
                ->with(['category', 'coverPicture'])
                ->orderBy('published_at', 'desc');

            if ($type) {
                $postsQuery->byType($type);
            }

            if ($categoryId) {
                $postsQuery->byCategory($categoryId);
            }

            $posts = $postsQuery->paginate($perPage)->toArray();
            $posts['data'] = array_map(function ($post) {
                return [
                    'id' => $post['id'],
                    'slug' => $post['slug'],
                    'type' => $post['type'],
                    'status' => $post['status'],
                    'category' => $post['category'],
                    'cover_picture' => $post['cover_picture'],
                    'published_at' => $post['published_at'],
                    'created_at' => $post['created_at'],
                    'updated_at' => $post['updated_at'],
                ];
            }, $posts['data']);
        }

        return response()->json([
            'drafts' => $drafts,
            'posts' => $posts,
        ]);
    }

    /**
     * Display the specified draft
     */
    public function showDraft(BlogPostDraft $draft): JsonResponse
    {
        $draft->load(['category', 'coverPicture', 'contents.content', 'gameReviewDraft.links']);

        return response()->json([
            'id' => $draft->id,
            'slug' => $draft->slug,
            'type' => $draft->type,
            'status' => 'draft',
            'category_id' => $draft->category_id,
            'category' => $draft->category,
            'cover_picture_id' => $draft->cover_picture_id,
            'cover_picture' => $draft->coverPicture,
            'blog_post_id' => $draft->blog_post_id,
            'contents' => $draft->contents,
            'game_review_draft' => $draft->gameReviewDraft,
            'created_at' => $draft->created_at,
            'updated_at' => $draft->updated_at,
        ]);
    }

    /**
     * Display the specified post
     */
    public function showPost(BlogPost $post): JsonResponse
    {
        $post->load(['category', 'coverPicture', 'contents.content', 'gameReview.links']);

        return response()->json([
            'id' => $post->id,
            'slug' => $post->slug,
            'type' => $post->type,
            'status' => $post->status,
            'category_id' => $post->category_id,
            'category' => $post->category,
            'cover_picture_id' => $post->cover_picture_id,
            'cover_picture' => $post->coverPicture,
            'published_at' => $post->published_at,
            'contents' => $post->contents,
            'game_review' => $post->gameReview,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ]);
    }

    /**
     * Store a newly created draft
     */
    public function storeDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'unique:blog_post_drafts,slug'],
            'type' => ['required', 'string', 'in:article,game_review'],
            'category_id' => ['required', 'exists:blog_categories,id'],
            'cover_picture_id' => ['nullable', 'exists:pictures,id'],
        ]);

        $draft = $this->blogService->createDraft($validated);

        return response()->json([
            'id' => $draft->id,
            'slug' => $draft->slug,
            'type' => $draft->type,
            'status' => 'draft',
            'category_id' => $draft->category_id,
            'cover_picture_id' => $draft->cover_picture_id,
            'blog_post_id' => $draft->blog_post_id,
            'created_at' => $draft->created_at,
            'updated_at' => $draft->updated_at,
        ], 201);
    }

    /**
     * Update the specified draft
     */
    public function updateDraft(Request $request, BlogPostDraft $draft): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('blog_post_drafts')->ignore($draft->id)],
            'type' => ['sometimes', 'string', 'in:article,game_review'],
            'category_id' => ['sometimes', 'exists:blog_categories,id'],
            'cover_picture_id' => ['nullable', 'exists:pictures,id'],
        ]);

        $draft = $this->blogService->updateDraft($draft, $validated);

        return response()->json([
            'id' => $draft->id,
            'slug' => $draft->slug,
            'type' => $draft->type,
            'status' => 'draft',
            'category_id' => $draft->category_id,
            'cover_picture_id' => $draft->cover_picture_id,
            'blog_post_id' => $draft->blog_post_id,
            'created_at' => $draft->created_at,
            'updated_at' => $draft->updated_at,
        ]);
    }

    /**
     * Remove the specified draft
     */
    public function destroyDraft(BlogPostDraft $draft): JsonResponse
    {
        $this->blogService->deleteDraft($draft);

        return response()->json(null, 204);
    }

    /**
     * Remove the specified post
     */
    public function destroyPost(BlogPost $post): JsonResponse
    {
        $this->blogService->deletePost($post);

        return response()->json(null, 204);
    }

    /**
     * Publish a draft
     */
    public function publishDraft(BlogPostDraft $draft): JsonResponse
    {
        $post = $this->blogService->publishDraft($draft);

        return response()->json([
            'id' => $post->id,
            'slug' => $post->slug,
            'type' => $post->type,
            'status' => $post->status,
            'category_id' => $post->category_id,
            'cover_picture_id' => $post->cover_picture_id,
            'published_at' => $post->published_at,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ]);
    }

    /**
     * Create a draft from an existing post
     */
    public function createDraftFromPost(BlogPost $post, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'copy_content' => ['sometimes', 'boolean'],
        ]);

        $copyContent = $validated['copy_content'] ?? false;
        $draft = $this->blogService->createDraftFromPost($post, $copyContent);

        return response()->json([
            'id' => $draft->id,
            'slug' => $draft->slug,
            'type' => $draft->type,
            'status' => 'draft',
            'category_id' => $draft->category_id,
            'cover_picture_id' => $draft->cover_picture_id,
            'blog_post_id' => $draft->blog_post_id,
            'created_at' => $draft->created_at,
            'updated_at' => $draft->updated_at,
        ], 201);
    }
}
