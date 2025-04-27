<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExperienceRequest;
use App\Models\Experience;
use App\Models\Translation;

class ExperienceController extends Controller
{
    public function index()
    {
        return Experience::with([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
        ])->get();
    }

    public function store(ExperienceRequest $request)
    {
        $titleTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->title);
        $shortDescriptionTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->short_description);
        $fullDescriptionTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->full_description);

        $experience = Experience::create([
            'title_translation_key_id' => $titleTranslation->translation_key_id,
            'organization_name' => $request->organization_name,
            'logo_id' => $request->logo_id,
            'type' => $request->type,
            'location' => $request->location,
            'website_url' => $request->website_url,
            'short_description_translation_key_id' => $shortDescriptionTranslation->translation_key_id,
            'full_description_translation_key_id' => $fullDescriptionTranslation->translation_key_id,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at,
        ])->load([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
        ]);

        if ($request->technologies) {
            $experience->technologies()->sync($request->technologies);
        }

        return $experience;
    }

    public function show(int $id)
    {
        $experience = Experience::findOrFail($id);

        return $experience->load([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
        ]);
    }

    public function update(ExperienceRequest $request, int $id)
    {
        $experience = Experience::findOrFail($id);

        $titleTranslation = Translation::createOrUpdate($experience->titleTranslationKey, $request->locale, $request->title);
        $shortDescriptionTranslation = Translation::createOrUpdate($experience->shortDescriptionTranslationKey, $request->locale, $request->short_description);
        $fullDescriptionTranslation = Translation::createOrUpdate($experience->fullDescriptionTranslationKey, $request->locale, $request->full_description);

        $experience->update([
            'title_translation_key_id' => $titleTranslation->translation_key_id,
            'organization_name' => $request->organization_name,
            'logo_id' => $request->logo_id,
            'type' => $request->type,
            'location' => $request->location,
            'website_url' => $request->website_url,
            'short_description_translation_key_id' => $shortDescriptionTranslation->translation_key_id,
            'full_description_translation_key_id' => $fullDescriptionTranslation->translation_key_id,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at,
        ]);

        if ($request->technologies) {
            $experience->technologies()->sync($request->technologies);
        } else {
            $experience->technologies()->detach();
        }

        return $experience;
    }

    public function destroy(int $id)
    {
        $experience = Experience::findOrFail($id);
        $experience->delete();

        return response()->noContent();
    }
}
