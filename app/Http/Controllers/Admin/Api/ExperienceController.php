<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExperienceRequest;
use App\Models\Experience;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ExperienceController extends Controller
{
    /**
     * @return Collection<int, Experience>
     */
    public function index(): Collection
    {
        return Experience::with([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
        ])->get();
    }

    public function store(ExperienceRequest $request): Experience
    {
        $titleTranslation = Translation::createOrUpdate(uniqid(), $request->input('locale'), $request->title);
        $shortDescriptionTranslation = Translation::createOrUpdate(uniqid(), $request->input('locale'), $request->short_description);
        $fullDescriptionTranslation = Translation::createOrUpdate(uniqid(), $request->input('locale'), $request->full_description);

        // Generate slug from organization name and title
        $baseSlug = Str::slug($request->organization_name.' '.$request->title);
        $slug = $baseSlug;
        $counter = 1;

        // Check for uniqueness and add numeric suffix if needed
        while (Experience::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $experience = Experience::create([
            'title_translation_key_id' => $titleTranslation->translation_key_id,
            'organization_name' => $request->organization_name,
            'slug' => $slug,
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

    public function show(int $id): Experience
    {
        $experience = Experience::findOrFail($id);

        return $experience->load([
            'titleTranslationKey.translations',
            'shortDescriptionTranslationKey.translations',
            'fullDescriptionTranslationKey.translations',
        ]);
    }

    public function update(ExperienceRequest $request, int $id): Experience
    {
        $experience = Experience::findOrFail($id);

        $titleTranslationKey = $experience->titleTranslationKey;
        $shortDescriptionTranslationKey = $experience->shortDescriptionTranslationKey;
        $fullDescriptionTranslationKey = $experience->fullDescriptionTranslationKey;

        $titleTranslation = Translation::createOrUpdate($titleTranslationKey ?? uniqid(), $request->input('locale'), $request->title);
        $shortDescriptionTranslation = Translation::createOrUpdate($shortDescriptionTranslationKey ?? uniqid(), $request->input('locale'), $request->short_description);
        $fullDescriptionTranslation = Translation::createOrUpdate($fullDescriptionTranslationKey ?? uniqid(), $request->input('locale'), $request->full_description);

        // Generate new slug if organization name or title changed
        $updateData = [
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
        ];

        // Only regenerate slug if organization name changed or slug doesn't exist
        if ($experience->organization_name !== $request->organization_name || ! $experience->slug) {
            $baseSlug = Str::slug($request->organization_name.' '.$request->title);
            $slug = $baseSlug;
            $counter = 1;

            // Check for uniqueness (excluding current experience)
            while (Experience::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }

            $updateData['slug'] = $slug;
        }

        $experience->update($updateData);

        if ($request->technologies) {
            $experience->technologies()->sync($request->technologies);
        } else {
            $experience->technologies()->detach();
        }

        return $experience;
    }

    public function destroy(int $id): Response
    {
        $experience = Experience::findOrFail($id);
        $experience->delete();

        return response()->noContent();
    }
}
