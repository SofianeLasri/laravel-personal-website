<?php

namespace Database\Seeders;

use App\Enums\CategoryColor;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Creation;
use App\Models\CreationDraft;
use App\Models\Experience;
use App\Models\OptimizedPicture;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('1. Creating technologies with optimized pictures');

        // Utiliser la nouvelle méthode createSet() pour créer un ensemble cohérent de technologies
        $technologies = Technology::factory()->createSet();
        $this->command->info('-- Created '.$technologies->count().' technologies with optimized icons');

        // Créer quelques technologies supplémentaires pour avoir plus de diversité
        $additionalTechnologies = Technology::factory()
            ->count(10)
            ->complete()
            ->create();

        $allTechnologies = collect($technologies)->merge($additionalTechnologies);

        $this->command->info('2. Creating creations with complete relations');

        // Créer des créations complètes avec la nouvelle méthode complete()
        $creations = Creation::factory()
            ->count(15)
            ->complete()
            ->create();

        $this->command->info('-- Created '.$creations->count().' complete creations');

        // Créer quelques créations avec des technologies spécifiques
        $specificCreations = Creation::factory()
            ->count(5)
            ->create()
            ->each(function ($creation) use ($allTechnologies) {
                // Attacher 3-5 technologies aléatoires
                $selectedTechs = $allTechnologies->random(rand(3, 5));
                $creation->technologies()->attach($selectedTechs);

                // Créer les optimized pictures
                $this->createOptimizedPicturesFor($creation->logo);
                $this->createOptimizedPicturesFor($creation->coverImage);
            });

        $this->command->info('-- Created '.$specificCreations->count().' creations with specific technologies');

        // Créer quelques brouillons
        $this->command->info('3. Creating drafts');

        $creations->random(3)->each(function ($creation) {
            CreationDraft::fromCreation($creation)->save();
        });

        $drafts = CreationDraft::factory()
            ->count(2)
            ->withPeople()
            ->withFeatures()
            ->withScreenshots()
            ->withTags()
            ->create()
            ->each(function ($draft) use ($allTechnologies) {
                $draft->technologies()->attach($allTechnologies->random(rand(2, 4)));
            });

        $this->command->info('-- Created '.$drafts->count().' new drafts');

        $this->command->info('4. Creating Experiences');

        $formations = Experience::factory()->formation()->count(3)->create();
        $emplois = Experience::factory()->emploi()->count(3)->create();

        $formations->each(function ($formation) use ($allTechnologies) {
            $formation->technologies()->attach($allTechnologies->random(rand(2, 4)));
        });

        $emplois->each(function ($emploi) use ($allTechnologies) {
            $emploi->technologies()->attach($allTechnologies->random(rand(3, 5)));
        });

        $this->command->info('-- Created '.($formations->count() + $emplois->count()).' experiences');

        $this->command->info('5. Creating Technology Experiences');

        $allTechnologies->random(5)->each(function ($technology) {
            TechnologyExperience::factory()->create([
                'technology_id' => $technology->id,
            ]);
        });

        $this->command->info('-- Created technology experiences');

        $this->command->info('6. Creating Blog Content');

        // Create blog categories with specific names and colors
        $technologyCategory = BlogCategory::factory()
            ->withNames(['fr' => 'Technologie', 'en' => 'Technology'], CategoryColor::BLUE)
            ->create();

        $gamingCategory = BlogCategory::factory()
            ->withNames(['fr' => 'Gaming', 'en' => 'Gaming'], CategoryColor::GREEN)
            ->create();

        $tutorialsCategory = BlogCategory::factory()
            ->withNames(['fr' => 'Tutoriels', 'en' => 'Tutorials'], CategoryColor::PURPLE)
            ->create();

        $newsCategory = BlogCategory::factory()
            ->withNames(['fr' => 'Actualités', 'en' => 'News'], CategoryColor::ORANGE)
            ->create();

        $reviewsCategory = BlogCategory::factory()
            ->withNames(['fr' => 'Critiques', 'en' => 'Reviews'], CategoryColor::RED)
            ->create();

        $categories = collect([$technologyCategory, $gamingCategory, $tutorialsCategory, $newsCategory, $reviewsCategory]);

        $this->command->info('-- Created '.$categories->count().' blog categories');

        // Create complete blog posts with multiple content sections
        $blogPosts = BlogPost::factory()
            ->count(10)
            ->withCompleteContent()
            ->create()
            ->each(function ($blogPost) use ($categories) {
                // Assign random category based on content type
                $category = $categories->random();
                $blogPost->update(['category_id' => $category->id]);

                // Create optimized pictures for cover images
                $this->createOptimizedPicturesFor($blogPost->coverPicture);
            });

        $this->command->info('-- Created '.$blogPosts->count().' complete blog posts with content');

        // Create some specific game review posts
        $gameReviews = BlogPost::factory()
            ->count(3)
            ->gameReview()
            ->withCompleteContent()
            ->create()
            ->each(function ($blogPost) use ($gamingCategory, $reviewsCategory) {
                // Assign to gaming or reviews category
                $gameCategory = collect([$gamingCategory, $reviewsCategory])->random();
                $blogPost->update(['category_id' => $gameCategory->id]);

                $this->createOptimizedPicturesFor($blogPost->coverPicture);
            });

        $this->command->info('-- Created '.$gameReviews->count().' game review posts');

        $this->command->info('7. Seeding complete!');
        $this->command->info('-- Total technologies: '.Technology::count());
        $this->command->info('-- Total creations: '.Creation::count());
        $this->command->info('-- Total drafts: '.CreationDraft::count());
        $this->command->info('-- Total blog posts: '.BlogPost::count());
        $this->command->info('-- Total blog categories: '.BlogCategory::count());
        $this->command->info('-- Technologies with creations: '.
            Technology::whereHas('creations')->count());
    }

    protected function createOptimizedPicturesFor($picture): void
    {
        if (! $picture) {
            return;
        }

        $formats = ['avif', 'webp', 'jpg'];
        $variants = ['thumbnail', 'small', 'medium', 'large', 'full'];

        foreach ($formats as $format) {
            foreach ($variants as $variant) {
                OptimizedPicture::create([
                    'picture_id' => $picture->id,
                    'format' => $format,
                    'variant' => $variant,
                    'path' => "uploads/optimized/{$picture->filename}_{$variant}.{$format}",
                ]);
            }
        }
    }
}
