<?php

namespace Database\Seeders;

use App\Enums\TechnologyType;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Technology;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
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

        $allTechnologies = $languageTechnologies->merge($frameworkTechnologies)->merge($libraryTechnologies);

        $attachRandomTechnologies = function ($model) use ($allTechnologies, $languageTechnologies, $frameworkTechnologies, $libraryTechnologies) {
            $selectedTechs = collect();

            $selectedTechs->push($languageTechnologies->random());

            $selectedTechs->push($frameworkTechnologies->random());

            if (rand(0, 1)) {
                $selectedTechs->push($libraryTechnologies->random());
            }

            $remainingCount = rand(1, 3);
            $remainingTechs = $allTechnologies->diff($selectedTechs)->random(min($remainingCount, $allTechnologies->diff($selectedTechs)->count()));
            $selectedTechs = $selectedTechs->merge($remainingTechs);

            $model->technologies()->attach($selectedTechs);
        };

        $creations = Creation::factory()
            ->withPeople()
            ->withFeatures()
            ->withScreenshots()
            ->withTags()
            ->count(15)
            ->create();

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

        $drafts->each($attachRandomTechnologies);

        $this->command->info('Optimizing pictures...');
        Artisan::call('optimize:pictures', [], $this->command->getOutput());
    }
}
