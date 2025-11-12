<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\CreationDraft;
use App\Models\CreationDraftContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreationDraftContentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'creation_draft_id' => 'required|integer|exists:creation_drafts,id',
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
        $order = $request->order ?? CreationDraftContent::where('creation_draft_id', $request->creation_draft_id)->max('order') + 1;

        $draftContent = CreationDraftContent::create([
            'creation_draft_id' => $request->creation_draft_id,
            'content_type' => $request->content_type,
            'content_id' => $request->content_id,
            'order' => $order,
        ]);

        $draftContent->load('content');

        return response()->json($draftContent, 201);
    }

    public function update(Request $request, CreationDraftContent $creationDraftContent): JsonResponse
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
            $creationDraftContent->update([
                'order' => $request->order,
            ]);
        }

        $creationDraftContent->load('content');

        return response()->json($creationDraftContent);
    }

    public function reorder(Request $request, CreationDraft $creationDraft): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content_ids' => 'required|array',
            'content_ids.*' => 'integer|exists:creation_draft_contents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request, $creationDraft) {
            foreach ($request->content_ids as $index => $contentId) {
                CreationDraftContent::where('id', $contentId)
                    ->where('creation_draft_id', $creationDraft->id)
                    ->update(['order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Content reordered successfully']);
    }

    public function destroy(CreationDraftContent $creationDraftContent): JsonResponse
    {
        $creationDraftContent->delete();

        return response()->json(['message' => 'Content deleted successfully']);
    }
}
