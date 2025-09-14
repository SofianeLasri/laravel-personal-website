<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Services\BlogContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogContentController extends Controller
{
    public function __construct(private readonly BlogContentService $contentService) {}

    /**
     * Display all content blocks for a draft
     */
    public function index(BlogPostDraft $draft): JsonResponse
    {
        $contents = $draft->contents()
            ->with('content')
            ->orderBy('order')
            ->get()
            ->map(function ($content) {
                return [
                    'id' => $content->id,
                    'content_type' => $content->content_type,
                    'content_id' => $content->content_id,
                    'order' => $content->order,
                    'content' => $content->content,
                ];
            });

        return response()->json(['data' => $contents]);
    }

    /**
     * Display a specific content block
     */
    public function show(BlogPostDraft $draft, BlogPostDraftContent $content): JsonResponse
    {
        // Ensure the content belongs to this draft
        if ($content->blog_post_draft_id !== $draft->id) {
            return response()->json(['message' => 'Content not found'], 404);
        }

        $content->load('content');

        return response()->json([
            'id' => $content->id,
            'content_type' => $content->content_type,
            'content_id' => $content->content_id,
            'order' => $content->order,
            'content' => $content->content,
        ]);
    }

    /**
     * Create a new markdown content block
     */
    public function storeMarkdown(Request $request, BlogPostDraft $draft): JsonResponse
    {
        $validated = $request->validate([
            'translation_key_id' => ['required', 'exists:translation_keys,id'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $validated['order'] ?? ($draft->contents()->max('order') ?? 0) + 1;

        $content = $this->contentService->createMarkdownContent(
            $draft,
            $validated['translation_key_id'],
            $order
        );

        $content->load('content');

        return response()->json([
            'id' => $content->id,
            'content_type' => $content->content_type,
            'content_id' => $content->content_id,
            'order' => $content->order,
            'content' => $content->content,
        ], 201);
    }

    /**
     * Create a new gallery content block
     */
    public function storeGallery(Request $request, BlogPostDraft $draft): JsonResponse
    {
        $validated = $request->validate([
            'layout' => ['required', 'string', 'in:grid,masonry,carousel'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:6'],
            'pictures' => ['nullable', 'array'],
            'pictures.*' => ['exists:pictures,id'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $validated['order'] ?? ($draft->contents()->max('order') ?? 0) + 1;

        $galleryData = [
            'layout' => $validated['layout'],
            'columns' => $validated['columns'] ?? null,
            'pictures' => $validated['pictures'] ?? [],
        ];

        $content = $this->contentService->createGalleryContent($draft, $galleryData, $order);

        $content->load('content.pictures');

        return response()->json([
            'id' => $content->id,
            'content_type' => $content->content_type,
            'content_id' => $content->content_id,
            'order' => $content->order,
            'content' => $content->content,
        ], 201);
    }

    /**
     * Create a new video content block
     */
    public function storeVideo(Request $request, BlogPostDraft $draft): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => ['required', 'exists:videos,id'],
            'caption_translation_key_id' => ['nullable', 'exists:translation_keys,id'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $validated['order'] ?? ($draft->contents()->max('order') ?? 0) + 1;

        $content = $this->contentService->createVideoContent(
            $draft,
            $validated['video_id'],
            $order,
            $validated['caption_translation_key_id'] ?? null
        );

        $content->load('content.video');

        return response()->json([
            'id' => $content->id,
            'content_type' => $content->content_type,
            'content_id' => $content->content_id,
            'order' => $content->order,
            'content' => $content->content,
        ], 201);
    }

    /**
     * Update a markdown content block
     */
    public function updateMarkdown(
        Request $request,
        BlogPostDraft $draft,
        BlogPostDraftContent $content
    ): JsonResponse {
        // Ensure the content belongs to this draft
        if ($content->blog_post_draft_id !== $draft->id) {
            return response()->json(['message' => 'Content not found'], 404);
        }

        $validated = $request->validate([
            'translation_key_id' => ['required', 'exists:translation_keys,id'],
        ]);

        $markdownContent = $this->contentService->updateMarkdownContent(
            $content->content,
            $validated['translation_key_id']
        );

        return response()->json([
            'id' => $content->id,
            'content_type' => $content->content_type,
            'content_id' => $content->content_id,
            'order' => $content->order,
            'content' => $markdownContent,
        ]);
    }

    /**
     * Update a gallery content block
     */
    public function updateGallery(
        Request $request,
        BlogPostDraft $draft,
        BlogPostDraftContent $content
    ): JsonResponse {
        // Ensure the content belongs to this draft
        if ($content->blog_post_draft_id !== $draft->id) {
            return response()->json(['message' => 'Content not found'], 404);
        }

        $validated = $request->validate([
            'layout' => ['required', 'string', 'in:grid,masonry,carousel'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:6'],
            'pictures' => ['nullable', 'array'],
            'pictures.*' => ['exists:pictures,id'],
        ]);

        $galleryContent = $this->contentService->updateGalleryContent(
            $content->content,
            $validated
        );

        return response()->json([
            'id' => $content->id,
            'content_type' => $content->content_type,
            'content_id' => $content->content_id,
            'order' => $content->order,
            'content' => $galleryContent,
        ]);
    }

    /**
     * Update a video content block
     */
    public function updateVideo(
        Request $request,
        BlogPostDraft $draft,
        BlogPostDraftContent $content
    ): JsonResponse {
        // Ensure the content belongs to this draft
        if ($content->blog_post_draft_id !== $draft->id) {
            return response()->json(['message' => 'Content not found'], 404);
        }

        $validated = $request->validate([
            'video_id' => ['required', 'exists:videos,id'],
            'caption_translation_key_id' => ['nullable', 'exists:translation_keys,id'],
        ]);

        $videoContent = $this->contentService->updateVideoContent(
            $content->content,
            $validated['video_id'],
            $validated['caption_translation_key_id'] ?? null
        );

        return response()->json([
            'id' => $content->id,
            'content_type' => $content->content_type,
            'content_id' => $content->content_id,
            'order' => $content->order,
            'content' => $videoContent,
        ]);
    }

    /**
     * Reorder content blocks
     */
    public function reorder(Request $request, BlogPostDraft $draft): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'exists:blog_post_draft_contents,id'],
        ]);

        // Verify all content IDs belong to this draft
        $contentIds = $draft->contents()->pluck('id')->toArray();
        $requestedIds = $validated['order'];

        if (count(array_diff($requestedIds, $contentIds)) > 0) {
            return response()->json(['message' => 'Invalid content IDs provided'], 422);
        }

        $this->contentService->reorderContent($draft, $validated['order']);

        return response()->json(['message' => 'Content reordered successfully']);
    }

    /**
     * Delete a content block
     */
    public function destroy(BlogPostDraft $draft, BlogPostDraftContent $content): JsonResponse
    {
        // Ensure the content belongs to this draft
        if ($content->blog_post_draft_id !== $draft->id) {
            return response()->json(['message' => 'Content not found'], 404);
        }

        $this->contentService->deleteContent($content);

        return response()->json(null, 204);
    }

    /**
     * Duplicate a content block
     */
    public function duplicate(BlogPostDraft $draft, BlogPostDraftContent $content): JsonResponse
    {
        // Ensure the content belongs to this draft
        if ($content->blog_post_draft_id !== $draft->id) {
            return response()->json(['message' => 'Content not found'], 404);
        }

        $duplicatedContent = $this->contentService->duplicateContent($content);
        $duplicatedContent->load('content');

        return response()->json([
            'id' => $duplicatedContent->id,
            'content_type' => $duplicatedContent->content_type,
            'content_id' => $duplicatedContent->content_id,
            'order' => $duplicatedContent->order,
            'content' => $duplicatedContent->content,
        ], 201);
    }
}
