<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Screenshot\ReorderCreationDraftScreenshotsRequest;
use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReorderCreationDraftScreenshotsController extends Controller
{
    /**
     * Reorder screenshots for a creation draft.
     *
     * @param  ReorderCreationDraftScreenshotsRequest  $request
     * @param  CreationDraft  $creationDraft
     * @return JsonResponse
     */
    public function __invoke(ReorderCreationDraftScreenshotsRequest $request, CreationDraft $creationDraft): JsonResponse
    {
        $screenshots = $request->input('screenshots');

        DB::transaction(function () use ($screenshots, $creationDraft) {
            // First, set all orders to negative values to avoid unique constraint violations
            $screenshotIds = array_column($screenshots, 'id');
            CreationDraftScreenshot::whereIn('id', $screenshotIds)
                ->update(['order' => DB::raw('id * -1')]);

            // Then update each screenshot to its final order
            foreach ($screenshots as $screenshot) {
                CreationDraftScreenshot::where('id', $screenshot['id'])
                    ->update(['order' => $screenshot['order']]);
            }
        });

        // Return updated screenshots with relationships
        $updatedScreenshots = CreationDraftScreenshot::where('creation_draft_id', $creationDraft->id)
            ->with(['picture', 'captionTranslationKey.translations'])
            ->orderBy('order')
            ->get();

        return response()->json($updatedScreenshots);
    }
}
