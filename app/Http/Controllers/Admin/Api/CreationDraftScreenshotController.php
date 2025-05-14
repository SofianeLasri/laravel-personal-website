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
        return response()->json($creationDraft->screenshots->load(['picture', 'captionTranslationKey.translations']));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCreationDraftScreenshotRequest $request, CreationDraft $creationDraft): JsonResponse
    {
        $translation = null;
        if ($request->filled('caption')) {
            $caption = $request->caption;
            $translation = Translation::createOrUpdate(uniqid(), $request->locale, $caption)->translation_key_id;
        }

        $creationDraftScreenshot = $creationDraft->screenshots()->create([
            'picture_id' => $request->picture_id,
            'caption_translation_key_id' => $translation,
        ])->load(['picture', 'captionTranslationKey.translations']);

        return response()->json($creationDraftScreenshot, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $creationDraftScreenshotId): JsonResponse
    {
        return response()->json(CreationDraftScreenshot::findOrFail($creationDraftScreenshotId)->load(['picture', 'captionTranslationKey.translations']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCreationDraftScreenshotRequest $request, int $creationDraftScreenshotId): JsonResponse
    {
        $creationDraftScreenshot = CreationDraftScreenshot::findOrFail($creationDraftScreenshotId);

        if ($request->filled('caption')) {
            $caption = $request->caption;
            $translationKey = uniqid();

            if ($creationDraftScreenshot->captionTranslationKey) {
                $translationKey = $creationDraftScreenshot->captionTranslationKey;
            }

            $translationKeyId = Translation::createOrUpdate($translationKey,
                $request->locale,
                $caption)->translation_key_id;

            $creationDraftScreenshot->update([
                'caption_translation_key_id' => $translationKeyId,
            ]);
        } else {
            $creationDraftScreenshot->captionTranslationKey()->delete();
        }

        return response()->json($creationDraftScreenshot->load(['picture', 'captionTranslationKey.translations']));
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
