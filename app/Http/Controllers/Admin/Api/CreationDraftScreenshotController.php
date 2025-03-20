<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreationDraftScreenshotRequest;
use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;

class CreationDraftScreenshotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CreationDraft $creationDraft): JsonResponse
    {
        return response()->json($creationDraft->screenshots);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreationDraftScreenshotRequest $request, CreationDraft $creationDraft): JsonResponse
    {
        $caption = Translation::createOrUpdate(uniqid(), $request->locale, $request->caption);
        $creationDraftScreenshot = $creationDraft->screenshots()->create([
            'picture_id' => $request->picture_id,
            'caption_translation_key_id' => $caption->translation_key_id,
        ]);

        return response()->json($creationDraftScreenshot);
    }

    /**
     * Display the specified resource.
     */
    public function show(CreationDraftScreenshot $creationDraftScreenshot): JsonResponse
    {
        return response()->json($creationDraftScreenshot);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreationDraftScreenshotRequest $request, CreationDraftScreenshot $creationDraftScreenshot): JsonResponse
    {
        $caption = Translation::createOrUpdate(uniqid(), $request->locale, $request->caption);
        $creationDraftScreenshot->update([
            'picture_id' => $request->picture_id,
            'caption_translation_key_id' => $caption->translation_key_id,
        ]);

        return response()->json($creationDraftScreenshot);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CreationDraftScreenshot $creationDraftScreenshot)
    {
        $creationDraftScreenshot->delete();
    }
}
