<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnologyExperienceRequest;
use App\Models\TechnologyExperience;
use App\Models\Translation;

class TechnologyExperienceController extends Controller
{
    public function index()
    {
        return TechnologyExperience::all();
    }

    public function store(TechnologyExperienceRequest $request)
    {
        $descriptionTranslation = Translation::createOrUpdate(uniqid(), $request->locale, $request->description);

        return TechnologyExperience::create([
            'technology_id' => $request->technology_id,
            'description_translation_key_id' => $descriptionTranslation->translation_key_id,
        ]);
    }

    public function show(TechnologyExperience $technologyExperience)
    {
        return $technologyExperience;
    }

    public function update(TechnologyExperienceRequest $request, TechnologyExperience $technologyExperience)
    {
        $descriptionTranslation = Translation::createOrUpdate($technologyExperience->descriptionTranslationKey, $request->locale, $request->description);
        $technologyExperience->update([
            'technology_id' => $request->technology_id,
            'description_translation_key_id' => $descriptionTranslation->translation_key_id,
        ]);

        return $technologyExperience;
    }

    public function destroy(TechnologyExperience $technologyExperience)
    {
        $technologyExperience->delete();

        return response()->noContent();
    }
}
