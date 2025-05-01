<?php

namespace Database\Seeders;

use App\Enums\TechnologyType;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Experience;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('1. Creating technologies');
        $this->command->info('-- Type languages...');
        $languages = [
            'JavaScript', 'Python', 'PHP', 'Java', 'C#', 'TypeScript',
            'Ruby', 'Go', 'Swift', 'Kotlin',
        ];
        $languageTechnologies = collect();

        foreach ($languages as $language) {
            $languageTechnologies->push(
                Technology::factory()->create([
                    'name' => $language,
                    'type' => TechnologyType::LANGUAGE,
                ])
            );
        }

        $this->command->info('-- Type frameworks...');
        $frameworks = [
            'Laravel', 'Symfony', 'Django', 'Flask', 'Ruby on Rails',
            'Express.js', 'Spring', 'Angular', 'React', 'Vue.js',
        ];
        $frameworkTechnologies = collect();

        foreach ($frameworks as $framework) {
            $frameworkTechnologies->push(
                Technology::factory()->create([
                    'name' => $framework,
                    'type' => TechnologyType::FRAMEWORK,
                ])
            );
        }

        $this->command->info('-- Type libraries...');
        $libraries = [
            'jQuery', 'Bootstrap', 'Tailwind CSS', 'Lodash', 'Moment.js',
            'Axios', 'Chart.js', 'Three.js', 'Socket.IO', 'Redux',
        ];
        $libraryTechnologies = collect();
        foreach ($libraries as $library) {
            $libraryTechnologies->push(
                Technology::factory()->create([
                    'name' => $library,
                    'type' => TechnologyType::LIBRARY,
                ])
            );
        }

        $this->command->info('-- Type game engines...');
        $gameEngines = [
            'Unity', 'Unreal Engine', 'Godot', 'CryEngine',
            'Amazon Lumberyard', 'Bevy', 'Source Engine', 'Source 2',
        ];
        $gameEngineTechnologies = collect();
        foreach ($gameEngines as $gameEngine) {
            $gameEngineTechnologies->push(
                Technology::factory()->create([
                    'name' => $gameEngine,
                    'type' => TechnologyType::GAME_ENGINE,
                ])
            );
        }

        $allTechnologies = $languageTechnologies->merge($frameworkTechnologies)->merge($libraryTechnologies);

        $attachRandomTechnologies = function ($model) use ($gameEngineTechnologies, $allTechnologies, $languageTechnologies, $frameworkTechnologies, $libraryTechnologies) {
            $selectedTechs = collect();

            $selectedTechs->push($languageTechnologies->random());

            $selectedTechs->push($frameworkTechnologies->random());

            if (rand(0, 1)) {
                $selectedTechs->push($libraryTechnologies->random());
            }
            if (rand(0, 1)) {
                $selectedTechs->push($gameEngineTechnologies->random());
            }

            $remainingCount = rand(1, 3);
            $remainingTechs = $allTechnologies->diff($selectedTechs)->random(min($remainingCount, $allTechnologies->diff($selectedTechs)->count()));
            $selectedTechs = $selectedTechs->merge($remainingTechs);

            $model->technologies()->attach($selectedTechs);
        };

        $this->command->info('2. Creating creations and drafts');

        $creations = Creation::factory()
            ->withPeople()
            ->withFeatures()
            ->withScreenshots()
            ->withTags()
            ->count(35)
            ->create();

        $this->command->info('-- Attaching random technologies to creations...');

        $creations->each($attachRandomTechnologies);

        $creations->random(3)->each(function ($creation) {
            CreationDraft::fromCreation($creation)->save();
        });

        $drafts = CreationDraft::factory()
            ->withPeople()
            ->withFeatures()
            ->withScreenshots()
            ->withTags()
            ->count(2)
            ->create();

        $this->command->info('-- Attaching random technologies to drafts...');
        $drafts->each($attachRandomTechnologies);

        $this->command->info('4. Creating Experiences');

        $formations = Experience::factory()->formation()->count(3)->create();
        $emplois = Experience::factory()->emploi()->count(3)->create();

        $this->command->info('-- Attaching random technologies to experiences...');

        $formations->each($attachRandomTechnologies);
        $emplois->each($attachRandomTechnologies);

        $this->command->info('5. Creating Technologies Experiences');

        $randomTechnologies = Technology::inRandomOrder()->take(3)->get();

        foreach ($randomTechnologies as $technology) {
            TechnologyExperience::factory()->create([
                'technology_id' => $technology->id,
            ]);
        }

        $this->command->info('6. Starting pictures optimization process');
        Artisan::call('optimize:pictures', [], $this->command->getOutput());
    }
}
