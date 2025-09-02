<?php

namespace Database\Seeders;

use App\Enums\TechnologyType;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Database\Seeder;

class DuskTestSeeder extends Seeder
{
    /**
     * Seed minimal data for Dusk browser tests.
     */
    public function run(): void
    {
        $this->command->info('Setting up minimal test data for Dusk tests...');

        // Create minimal technologies with translation keys
        $this->command->info('Creating essential technologies...');

        // Create Laravel technology
        $laravelTech = Technology::factory()->create([
            'name' => 'Laravel',
            'type' => TechnologyType::FRAMEWORK,
        ]);

        // Create PHP technology
        $phpTech = Technology::factory()->create([
            'name' => 'PHP',
            'type' => TechnologyType::LANGUAGE,
        ]);

        // Create JavaScript technology
        $jsTech = Technology::factory()->create([
            'name' => 'JavaScript',
            'type' => TechnologyType::LANGUAGE,
        ]);

        // Create Vue.js technology
        $vueTech = Technology::factory()->create([
            'name' => 'Vue.js',
            'type' => TechnologyType::FRAMEWORK,
        ]);

        // Create minimal creations (projects)
        $this->command->info('Creating minimal projects...');

        // Create a simple project
        $project = Creation::factory()->create([
            'name' => 'Portfolio Website',
            'slug' => 'portfolio-website',
            'type' => 'website',
            'is_published' => true,
        ]);

        // Attach technologies to the project
        $project->technologies()->attach([$laravelTech->id, $phpTech->id, $vueTech->id]);

        // Create another project
        $project2 = Creation::factory()->create([
            'name' => 'E-commerce Platform',
            'slug' => 'ecommerce-platform',
            'type' => 'application',
            'is_published' => true,
        ]);

        $project2->technologies()->attach([$laravelTech->id, $phpTech->id, $jsTech->id]);

        // Create minimal experience
        $this->command->info('Creating minimal experience...');

        $experience = Experience::factory()->emploi()->create([
            'title' => 'Développeur Full-Stack',
            'company' => 'Tech Company',
        ]);

        $experience->technologies()->attach([$laravelTech->id, $phpTech->id]);

        // Create basic translation keys for UI elements
        $this->command->info('Creating UI translations...');

        // Create translation keys for homepage content
        $devKey = TranslationKey::create(['key' => 'developer.title']);
        Translation::create([
            'translation_key_id' => $devKey->id,
            'locale' => 'fr',
            'value' => 'Développeur',
        ]);
        Translation::create([
            'translation_key_id' => $devKey->id,
            'locale' => 'en',
            'value' => 'Developer',
        ]);

        $fullStackKey = TranslationKey::create(['key' => 'developer.fullstack']);
        Translation::create([
            'translation_key_id' => $fullStackKey->id,
            'locale' => 'fr',
            'value' => 'Full-Stack',
        ]);
        Translation::create([
            'translation_key_id' => $fullStackKey->id,
            'locale' => 'en',
            'value' => 'Full-Stack',
        ]);

        $projectsKey = TranslationKey::create(['key' => 'navigation.projects']);
        Translation::create([
            'translation_key_id' => $projectsKey->id,
            'locale' => 'fr',
            'value' => 'Projets',
        ]);
        Translation::create([
            'translation_key_id' => $projectsKey->id,
            'locale' => 'en',
            'value' => 'Projects',
        ]);

        $this->command->info('Dusk test data seeding completed!');
    }
}
