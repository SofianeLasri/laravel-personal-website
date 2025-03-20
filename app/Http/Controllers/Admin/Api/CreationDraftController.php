<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreationDraftRequest;
use App\Models\CreationDraft;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;

class CreationDraftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CreationDraft::all());
    }

    public function store(CreationDraftRequest $request): JsonResponse
    {
        $shortDescriptionTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->short_description_content);
        $fullDescriptionTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->full_description_content);

        $draft = CreationDraft::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'logo_id' => $request->logo_id,
            'cover_image_id' => $request->cover_image_id,
            'type' => $request->type,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at,
            'short_description_translation_key_id' => $shortDescriptionTranslation->translation_key_id,
            'full_description_translation_key_id' => $fullDescriptionTranslation->translation_key_id,
            'external_url' => $request->external_url,
            'source_code_url' => $request->source_code_url,
            'original_creation_id' => $request->original_creation_id,
        ]);

        if ($request->has('people')) {
            $draft->people()->sync($request->people);
        }

        if ($request->has('technologies')) {
            $draft->technologies()->sync($request->technologies);
        }

        if ($request->has('tags')) {
            $draft->tags()->sync($request->tags);
        }

        return response()->json([
            'draft' => $draft,
        ]);
    }

    public function show(CreationDraft $creationDraft): JsonResponse
    {
        return response()->json($creationDraft);
    }

    public function update(CreationDraftRequest $request, CreationDraft $creationDraft): JsonResponse
    {
        $shortDescriptionTranslation = Translation::createOrUpdate($creationDraft->shortDescriptionTranslationKey, $request->locale, $request->short_description_content);
        $fullDescriptionTranslation = Translation::createOrUpdate($creationDraft->fullDescriptionTranslationKey, $request->locale, $request->full_description_content);

        $creationDraft->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'logo_id' => $request->logo_id,
            'cover_image_id' => $request->cover_image_id,
            'type' => $request->type,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at,
            'short_description_translation_key_id' => $shortDescriptionTranslation->translation_key_id,
            'full_description_translation_key_id' => $fullDescriptionTranslation->translation_key_id,
            'external_url' => $request->external_url,
            'source_code_url' => $request->source_code_url,
            'original_creation_id' => $request->original_creation_id,
        ]);

        if ($request->has('people')) {
            $creationDraft->people()->sync($request->people);
        }

        if ($request->has('technologies')) {
            $creationDraft->technologies()->sync($request->technologies);
        }

        if ($request->has('tags')) {
            $creationDraft->tags()->sync($request->tags);
        }

        return response()->json($creationDraft);
    }

    public function destroy(CreationDraft $creationDraft): JsonResponse
    {
        $creationDraft->delete();

        return response()->json([], 204);
    }
}
