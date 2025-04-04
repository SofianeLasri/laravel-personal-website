<?php

namespace Database\Seeders;

use App\Models\Creation;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Creation::factory()
            ->withPeople()
            ->withFeatures()
            ->withScreenshots()
            ->withTags()
            ->withTechnologies()
            ->count(50)
            ->create();
    }
}
