<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Screenshot\CreateCreationDraftScreenshotRequest;
use App\Http\Requests\Screenshot\UpdateCreationDraftScreenshotRequest;
use App\Models\CreationDraft;
use App\Models\CreationDraftScreenshot;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
    public function store(CreateCreationDraftScreenshotRequest $request, CreationDraft $creationDraft): JsonResponse
    {
        if ($request->has('caption')) {
            $caption = Translation::createOrUpdate(uniqid(), $request->locale, $request->caption);
        }
        $creationDraftScreenshot = $creationDraft->screenshots()->create([
            'picture_id' => $request->picture_id,
            'caption_translation_key_id' => $caption->translation_key_id,
        ]);

        return response()->json($creationDraftScreenshot, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $creationDraftScreenshotId): JsonResponse
    {
        return response()->json(CreationDraftScreenshot::findOrFail($creationDraftScreenshotId));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCreationDraftScreenshotRequest $request, int $creationDraftScreenshotId): JsonResponse
    {
        $creationDraftScreenshot = CreationDraftScreenshot::findOrFail($creationDraftScreenshotId);

        if ($request->has('caption')) {
            $caption = Translation::createOrUpdate($creationDraftScreenshot->captionTranslationKey ? $creationDraftScreenshot->captionTranslationKey : uniqid(),
                $request->locale,
                $request->caption);

            $creationDraftScreenshot->update([
                'caption_translation_key_id' => $caption->translation_key_id,
            ]);
        }

        $creationDraftScreenshot->update([
            'picture_id' => $request->picture_id,
        ]);

        return response()->json($creationDraftScreenshot);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $creationDraftScreenshotId): Response
    {
        CreationDraftScreenshot::findOrFail($creationDraftScreenshotId)->delete();

        return response()->noContent();
    }
}
