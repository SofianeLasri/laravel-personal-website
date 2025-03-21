<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feature\CreateCreationDraftFeatureRequest;
use App\Http\Requests\Feature\UpdateCreationDraftFeatureRequest;
use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreationDraftFeatureController extends Controller
{
    public function index(CreationDraft $creationDraft): JsonResponse
    {
        return response()->json($creationDraft->features);
    }

    public function store(CreateCreationDraftFeatureRequest $request, CreationDraft $creationDraft): JsonResponse
    {
        $titleTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->title);
        $descriptionTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->description);
        $creationDraftFeature = $creationDraft->features()->create([
            'title_translation_key_id' => $titleTranslation->id,
            'description_translation_key_id' => $descriptionTranslation->id,
            'picture_id' => $request->picture_id,
        ]);

        return response()->json($creationDraftFeature, Response::HTTP_CREATED);
    }

    public function show(int $creationDraftFeatureId): JsonResponse
    {
        return response()->json(CreationDraftFeature::findOrFail($creationDraftFeatureId));
    }

    public function update(UpdateCreationDraftFeatureRequest $request, int $creationDraftFeatureId): JsonResponse
    {
        $creationDraftFeature = CreationDraftFeature::findOrFail($creationDraftFeatureId);

        if ($request->has('title')) {
            Translation::createOrUpdate($creationDraftFeature->titleTranslationKey, $request->locale, $request->title);
        }

        if ($request->has('description')) {
            Translation::createOrUpdate($creationDraftFeature->descriptionTranslationKey, $request->locale, $request->description);
        }

        $creationDraftFeature->update([
            'picture_id' => $request->picture_id,
        ]);

        return response()->json($creationDraftFeature);
    }

    public function destroy(int $creationDraftFeatureId): Response
    {
        CreationDraftFeature::findOrFail($creationDraftFeatureId)->delete();

        return response()->noContent();
    }
}
