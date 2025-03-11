<?php

namespace Database\Factories;

use App\Models\CreationDraft;
use App\Models\CreationDraftFeature;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CreationDraftFeatureFactory extends Factory
{
    protected $model = CreationDraftFeature::class;

    public function definition(): array
    {
        return [
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'creation_draft_id' => CreationDraft::factory(),
            'title_translation_key_id' => TranslationKey::factory(),
            'description_translation_key_id' => TranslationKey::factory(),
            'picture_id' => Picture::factory(),
        ];
    }
}
