<?php

namespace Database\Factories;

use App\Enums\CreationType;
use App\Models\CreationDraft;
use App\Models\Picture;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CreationDraftFactory extends Factory
{
    protected $model = CreationDraft::class;

    public function definition(): array
    {
        $name = $this->getUniqueName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'logo_id' => Picture::factory(),
            'cover_image_id' => Picture::factory(),
            'type' => $this->faker->randomElement(CreationType::values()),
            'started_at' => $this->faker->date(),
            'ended_at' => $this->faker->optional(0.7)->date(),
            'short_description_translation_key_id' => TranslationKey::factory(),
            'full_description_translation_key_id' => TranslationKey::factory(),
            'external_url' => $this->faker->optional(0.8)->url(),
            'source_code_url' => $this->faker->optional(0.6)->url(),
            'featured' => $this->faker->boolean(20),
            'original_creation_id' => null,

            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    private function getUniqueName(): string
    {
        $name = $this->faker->catchPhrase();
        $testName = $name;
        $count = 0;
        while (CreationDraft::where('slug', Str::slug($testName))->exists()) {
            $testName = $name.' '.++$count;
        }

        return $testName;
    }
}
