<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Technology;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TechnologyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Technology::with(['descriptionTranslationKey.translations'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:technologies,name'],
            'type' => ['required', 'string', 'in:framework,library,language,other'],
            'svg_icon' => ['required', 'string'],
            'featured' => ['boolean'],
            'locale' => ['required', 'string', 'in:en,fr'],
            'description' => ['required', 'string'],
        ]);

        // Créer la clé de traduction pour la description
        $descriptionTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->description);

        // Créer la technologie
        $technology = Technology::create([
            'name' => $request->name,
            'type' => $request->type,
            'svg_icon' => $request->svg_icon,
            'featured' => $request->featured ?? false,
            'description_translation_key_id' => $descriptionTranslation->id,
        ]);

        return response()->json($technology->load('descriptionTranslationKey.translations'), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Technology $technology): JsonResponse
    {
        return response()->json($technology->load(['descriptionTranslationKey.translations']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Technology $technology): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:technologies,name,'.$technology->id],
            'type' => ['sometimes', 'string', 'in:framework,library,language,other'],
            'svg_icon' => ['sometimes', 'string'],
            'featured' => ['sometimes', 'boolean'],
            'locale' => ['required_with:description', 'string', 'in:en,fr'],
            'description' => ['sometimes', 'string'],
        ]);

        // Mettre à jour la traduction de la description si fournie
        if ($request->has('description')) {
            Translation::createOrUpdate($technology->descriptionTranslationKey, $request->locale, $request->description);
        }

        // Mettre à jour les autres champs
        $technology->update($request->only(['name', 'type', 'svg_icon', 'featured']));

        return response()->json($technology->fresh(['descriptionTranslationKey.translations']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Technology $technology): Response
    {
        $technology->delete();

        return response()->noContent();
    }

    /**
     * Check if a technology has any associations with creations or creation drafts.
     */
    public function checkAssociations(Technology $technology): JsonResponse
    {
        $hasCreations = $technology->creations()->exists();
        $hasCreationDrafts = $technology->creationDrafts()->exists();

        return response()->json([
            'has_associations' => $hasCreations || $hasCreationDrafts,
            'creations_count' => $technology->creations()->count(),
            'creation_drafts_count' => $technology->creationDrafts()->count(),
        ]);
    }
}
