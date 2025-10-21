<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BlogPostDraftContentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'blog_post_draft_id' => 'required|integer|exists:blog_post_drafts,id',
            'content_type' => 'required|string',
            'content_id' => 'required|integer',
            'order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // If no order specified, add at the end
        $order = $request->order ?? BlogPostDraftContent::where('blog_post_draft_id', $request->blog_post_draft_id)->max('order') + 1;

        $draftContent = BlogPostDraftContent::create([
            'blog_post_draft_id' => $request->blog_post_draft_id,
            'content_type' => $request->content_type,
            'content_id' => $request->content_id,
            'order' => $order,
        ]);

        $draftContent->load('content');

        return response()->json($draftContent, 201);
    }

    public function update(Request $request, BlogPostDraftContent $blogPostDraftContent): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('order')) {
            $blogPostDraftContent->update([
                'order' => $request->order,
            ]);
        }

        $blogPostDraftContent->load('content');

        return response()->json($blogPostDraftContent);
    }

    public function reorder(Request $request, BlogPostDraft $blogPostDraft): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content_ids' => 'required|array',
            'content_ids.*' => 'integer|exists:blog_post_draft_contents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request, $blogPostDraft) {
            foreach ($request->content_ids as $index => $contentId) {
                BlogPostDraftContent::where('id', $contentId)
                    ->where('blog_post_draft_id', $blogPostDraft->id)
                    ->update(['order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Content reordered successfully']);
    }

    public function destroy(BlogPostDraftContent $blogPostDraftContent): JsonResponse
    {
        $blogPostDraftContent->delete();

        return response()->json(['message' => 'Content deleted successfully']);
    }
}
