<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\TechnologyType;
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
        return response()->json(Technology::with(['descriptionTranslationKey.translations', 'iconPicture'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:technologies,name'],
            'type' => ['required', 'string', 'in:'.implode(',', TechnologyType::values())],
            'icon_picture_id' => ['required', 'integer', 'exists:pictures,id'],
            'locale' => ['required', 'string', 'in:en,fr'],
            'description' => ['required', 'string'],
        ]);

        $descriptionTranslation = Translation::createOrUpdate(uniqid(), $request->input('locale'), $request->description);

        $technology = Technology::create([
            'name' => $request->name,
            'type' => $request->type,
            'icon_picture_id' => $request->icon_picture_id,
            'description_translation_key_id' => $descriptionTranslation->translation_key_id,
        ]);

        return response()->json($technology->load(['descriptionTranslationKey.translations', 'iconPicture']), Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Technology $technology): JsonResponse
    {
        return response()->json($technology->load(['descriptionTranslationKey.translations', 'iconPicture']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Technology $technology): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', TechnologyType::values())],
            'icon_picture_id' => ['sometimes', 'integer', 'exists:pictures,id'],
            'locale' => ['required_with:description', 'string', 'in:en,fr'],
            'description' => ['sometimes', 'string'],
        ]);

        if ($request->has('name') && $technology->name !== $request->name) {
            $request->validate([
                'name' => ['unique:technologies,name'],
            ]);
        }

        if ($request->has('description')) {
            Translation::createOrUpdate($technology->descriptionTranslationKey, $request->input('locale'), $request->description);
        }

        $updateData = $request->only(['name', 'type', 'icon_picture_id']);

        $technology->update($updateData);

        return response()->json($technology->fresh(['descriptionTranslationKey.translations', 'iconPicture']));
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
