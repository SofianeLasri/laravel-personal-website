<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreationDraftRequest;
use App\Models\CreationDraft;
use App\Models\Person;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        return response()->json($draft, Response::HTTP_CREATED);
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

    public function destroy(CreationDraft $creationDraft): Response
    {
        $creationDraft->delete();

        return response()->noContent();
    }

    public function attachPerson(Request $request, CreationDraft $creationDraft): JsonResponse
    {
        $request->validate([
            'person_id' => ['required', 'exists:people,id'],
        ]);

        $personId = $request->input('person_id');

        if (! $creationDraft->people()->where('people.id', $personId)->exists()) {
            $creationDraft->people()->attach($personId);
        }

        return response()->json([
            'message' => 'Person attached successfully',
            'person' => Person::find($personId),
        ]);
    }

    public function detachPerson(Request $request, CreationDraft $creationDraft): JsonResponse
    {
        $request->validate([
            'person_id' => ['required', 'exists:people,id'],
        ]);

        $personId = $request->input('person_id');
        $creationDraft->people()->detach($personId);

        return response()->json([
            'message' => 'Person detached successfully',
        ]);
    }

    public function getPeople(CreationDraft $creationDraft): JsonResponse
    {
        return response()->json($creationDraft->people->load('picture'));
    }

    /**
     * Attach a tag to the creation draft.
     */
    public function attachTag(Request $request, CreationDraft $creationDraft): JsonResponse
    {
        $request->validate([
            'tag_id' => ['required', 'exists:tags,id'],
        ]);

        $tagId = $request->input('tag_id');

        // Vérifier si le tag est déjà attaché pour éviter les doublons
        if (! $creationDraft->tags()->where('tags.id', $tagId)->exists()) {
            $creationDraft->tags()->attach($tagId);
        }

        return response()->json([
            'message' => 'Tag attached successfully',
            'tag' => Tag::find($tagId),
        ]);
    }

    /**
     * Detach a tag from the creation draft.
     */
    public function detachTag(Request $request, CreationDraft $creationDraft): JsonResponse
    {
        $request->validate([
            'tag_id' => ['required', 'exists:tags,id'],
        ]);

        $tagId = $request->input('tag_id');
        $creationDraft->tags()->detach($tagId);

        return response()->json([
            'message' => 'Tag detached successfully',
        ]);
    }

    /**
     * Get all tags attached to the creation draft.
     */
    public function getTags(CreationDraft $creationDraft): JsonResponse
    {
        return response()->json($creationDraft->tags);
    }

    public function attachTechnology(Request $request, CreationDraft $creationDraft): JsonResponse
    {
        $request->validate([
            'technology_id' => ['required', 'exists:technologies,id'],
        ]);

        $technologyId = $request->input('technology_id');

        if (! $creationDraft->technologies()->where('technologies.id', $technologyId)->exists()) {
            $creationDraft->technologies()->attach($technologyId);
        }

        return response()->json([
            'message' => 'Technology attached successfully',
            'technology' => Technology::with(['descriptionTranslationKey.translations'])->find($technologyId),
        ]);
    }

    public function detachTechnology(Request $request, CreationDraft $creationDraft): JsonResponse
    {
        $request->validate([
            'technology_id' => ['required', 'exists:technologies,id'],
        ]);

        $technologyId = $request->input('technology_id');
        $creationDraft->technologies()->detach($technologyId);

        return response()->json([
            'message' => 'Technology detached successfully',
        ]);
    }

    public function getTechnologies(CreationDraft $creationDraft): JsonResponse
    {
        return response()->json($creationDraft->technologies()->with(['descriptionTranslationKey.translations'])->get());
    }
}
