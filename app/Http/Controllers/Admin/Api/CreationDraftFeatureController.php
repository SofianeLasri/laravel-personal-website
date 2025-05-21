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
        return response()->json($creationDraft->features->load(['picture', 'titleTranslationKey.translations', 'descriptionTranslationKey.translations']));
    }

    public function store(CreateCreationDraftFeatureRequest $request, int $creationDraftId): JsonResponse
    {
        $creationDraft = CreationDraft::findOrFail($creationDraftId);

        $titleTranslation = Translation::createOrUpdate(uniqid(), $request->input('locale'), $request->title);
        $descriptionTranslation = Translation::createOrUpdate(uniqid(), $request->input('locale'), $request->description);
        $creationDraftFeature = $creationDraft->features()->create([
            'title_translation_key_id' => $titleTranslation->translation_key_id,
            'description_translation_key_id' => $descriptionTranslation->translation_key_id,
            'picture_id' => $request->picture_id,
        ])->load(['picture', 'titleTranslationKey.translations', 'descriptionTranslationKey.translations']);

        return response()->json($creationDraftFeature, Response::HTTP_CREATED);
    }

    public function show(int $creationDraftFeatureId): JsonResponse
    {
        return response()->json(CreationDraftFeature::findOrFail($creationDraftFeatureId)->load(['picture', 'titleTranslationKey.translations', 'descriptionTranslationKey.translations']));
    }

    public function update(UpdateCreationDraftFeatureRequest $request, int $creationDraftFeatureId): JsonResponse
    {
        $creationDraftFeature = CreationDraftFeature::findOrFail($creationDraftFeatureId);

        if ($request->has('title')) {
            Translation::createOrUpdate($creationDraftFeature->titleTranslationKey, $request->input('locale'), $request->title);
        }

        if ($request->has('description')) {
            Translation::createOrUpdate($creationDraftFeature->descriptionTranslationKey, $request->input('locale'), $request->description);
        }

        if ($request->has('picture_id')) {
            $creationDraftFeature->update([
                'picture_id' => $request->picture_id,
            ]);
        }

        return response()->json($creationDraftFeature->load(['picture', 'titleTranslationKey.translations', 'descriptionTranslationKey.translations']));
    }

    public function destroy(int $creationDraftFeatureId): Response
    {
        CreationDraftFeature::findOrFail($creationDraftFeatureId)->delete();

        return response()->noContent();
    }
}
