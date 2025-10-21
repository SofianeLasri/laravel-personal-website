<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnologyExperienceRequest;
use App\Models\TechnologyExperience;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

class TechnologyExperienceController extends Controller
{
    /**
     * @return Collection<int, TechnologyExperience>
     */
    public function index(): Collection
    {
        return TechnologyExperience::all();
    }

    public function store(TechnologyExperienceRequest $request): TechnologyExperience
    {
        $descriptionTranslation = Translation::createOrUpdate(uniqid(), $request->input('locale'), $request->description);

        return TechnologyExperience::create([
            'technology_id' => $request->technology_id,
            'description_translation_key_id' => $descriptionTranslation->translation_key_id,
        ]);
    }

    public function show(TechnologyExperience $technologyExperience): TechnologyExperience
    {
        return $technologyExperience;
    }

    public function update(TechnologyExperienceRequest $request, TechnologyExperience $technologyExperience): TechnologyExperience
    {
        $descriptionTranslation = Translation::createOrUpdate($technologyExperience->descriptionTranslationKey, $request->input('locale'), $request->description);
        $technologyExperience->update([
            'technology_id' => $request->technology_id,
            'description_translation_key_id' => $descriptionTranslation->translation_key_id,
        ]);

        return $technologyExperience;
    }

    public function destroy(TechnologyExperience $technologyExperience): Response
    {
        $technologyExperience->delete();

        return response()->noContent();
    }
}
