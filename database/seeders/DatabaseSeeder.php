<?php

namespace Database\Seeders;

use App\Models\Creation;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\CreationDraft;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $creations = Creation::factory()
            ->withPeople()
            ->withFeatures()
            ->withScreenshots()
            ->withTags()
            ->withTechnologies()
            ->count(50)
            ->create();

        $creations->random(10)->each(function ($creation) {
            CreationDraft::fromCreation($creation)->save();
        });

        CreationDraft::factory()
            ->withPeople()
            ->withFeatures()
            ->withScreenshots()
            ->withTags()
            ->withTechnologies()
            ->count(15)
            ->create();
    }
}
