<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogPostDraft;
use App\Services\BlogPostConversionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlogPostController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(BlogPost::all());
    }

    public function store(Request $request, BlogPostConversionService $conversionService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'draft_id' => 'required|integer|exists:blog_post_drafts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $draft = BlogPostDraft::with([
                'titleTranslationKey.translations',
                'category',
                'coverPicture',
                'contents.content',
                'gameReviewDraft.links',
                'originalBlogPost',
            ])->findOrFail($request->draft_id);

            $blogPost = $conversionService->convertDraftToBlogPost($draft);

            return response()->json([
                'message' => 'Blog post published successfully',
                'blog_post' => $blogPost->load([
                    'titleTranslationKey.translations',
                    'category',
                    'coverPicture',
                    'contents.content',
                    'gameReview.links',
                ]),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to publish blog post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(BlogPost $blogPost) {}

    public function update(Request $request, BlogPost $blogPost) {}

    public function destroy(BlogPost $blogPost) {}
}
