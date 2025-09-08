<?php

namespace Tests\Browser;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Models\Creation;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\TranslationKey;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectsPageTest extends DuskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create some test data
        $this->createTestData();
    }

    /**
     * Test that the projects page loads successfully.
     */
    public function test_projects_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->assertPathIs('/projects')
                ->waitForText('Projets', 10)
                ->assertSee('Projets')
                ->assertPresent('[data-testid="project-filter"]')
                ->assertPresent('[data-testid="project-cards-container"]');
        });
    }

    /**
     * Test that projects are displayed on the page.
     */
    public function test_projects_are_displayed(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitForText('Projets')
                ->assertPresent('[data-testid="project-cards-container"]')
                ->screenshot('projects-page-final');
        });
    }

    /**
     * Test tab navigation between development, games, and source-engine.
     */
    public function test_tab_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitForText('Développement', 10)
                // Check development tab is active by default
                ->assertPresent('[data-tab="development"][data-active="true"]')
                // Click on games tab
                ->click('[data-tab="games"]')
                ->pause(500)
                ->assertPresent('[data-tab="games"][data-active="true"]')
                ->assertQueryStringHas('tab', 'games')
                // Click on source-engine tab
                ->click('[data-tab="source-engine"]')
                ->pause(500)
                ->assertPresent('[data-tab="source-engine"][data-active="true"]')
                ->assertQueryStringHas('tab', 'source-engine')
                // Go back to development tab
                ->click('[data-tab="development"]')
                ->pause(500)
                ->assertPresent('[data-tab="development"][data-active="true"]')
                ->assertQueryStringHas('tab', 'development');
        });
    }

    /**
     * Test framework filter functionality.
     */
    public function test_framework_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitFor('[data-testid="project-filter"]', 10)
                ->waitFor('[data-filter-type="framework"]', 10)
                // Click on Laravel framework filter
                ->click('[data-filter-type="framework"][data-filter-value="laravel"]')
                ->pause(500)
                ->assertQueryStringHas('frameworks')
                // Verify filtered projects are shown
                ->assertPresent('[data-testid="project-card"]')
                // Click again to deselect
                ->click('[data-filter-type="framework"][data-filter-value="laravel"][data-selected="true"]')
                ->pause(500)
                ->assertQueryStringMissing('frameworks');
        });
    }

    /**
     * Test multiple filters can be selected.
     */
    public function test_multiple_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitFor('[data-testid="project-filter"]', 10)
                ->waitFor('[data-filter-type="framework"]', 10)
                // Select Laravel framework
                ->click('[data-filter-type="framework"][data-filter-value="laravel"]')
                ->pause(300)
                // Select React framework
                ->click('[data-filter-type="framework"][data-filter-value="react"]')
                ->pause(500)
                // Check URL contains filters
                ->assertQueryStringHas('frameworks');
        });
    }

    /**
     * Test clear filters functionality.
     */
    public function test_clear_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitFor('[data-testid="project-filter"]', 10)
                ->waitFor('[data-filter-type="framework"]', 10)
                // Apply some filters
                ->click('[data-filter-type="framework"][data-filter-value="laravel"]')
                ->pause(300)
                ->click('[data-filter-type="framework"][data-filter-value="react"]')
                ->pause(300)
                // Clear filters
                ->click('[data-testid="clear-filters-button"]')
                ->pause(500)
                ->assertQueryStringMissing('frameworks');
        });
    }

    /**
     * Test navigation to project detail page.
     */
    public function test_navigation_to_project_detail(): void
    {
        $creation = Creation::where('type', CreationType::WEBSITE->value)->first();

        // S'assurer qu'on a bien une création
        $this->assertNotNull($creation, 'No website creation found in database');

        $this->browse(function (Browser $browser) use ($creation) {
            $browser->visit('/projects/'.$creation->slug)
                ->assertPathIs('/projects/'.$creation->slug)
                ->waitForText($creation->name, 10)
                ->assertSee($creation->name)
                // Retourner à la liste des projets
                ->visit('/projects')
                ->assertPathIs('/projects');
        });
    }

    /**
     * Test that filtered projects persist on page refresh.
     */
    public function test_filters_persist_on_refresh(): void
    {
        // Get IDs of Laravel technology
        $laravel = Technology::where('name', 'Laravel')->first();
        $react = Technology::where('name', 'React')->first();

        $this->assertNotNull($laravel, 'Laravel technology not found');
        $this->assertNotNull($react, 'React technology not found');

        $this->browse(function (Browser $browser) use ($laravel, $react) {
            $browser->visit("/projects?tab=development&frameworks={$laravel->id},{$react->id}")
                ->waitFor('[data-testid="project-filter"]', 10)
                // Verify tab is selected
                ->assertPresent('[data-tab="development"][data-active="true"]')
                // Verify filters are selected
                ->assertPresent('[data-filter-type="framework"][data-filter-value="laravel"][data-selected="true"]')
                ->assertPresent('[data-filter-type="framework"][data-filter-value="react"][data-selected="true"]');
        });
    }

    /**
     * Test responsive behavior of project grid.
     */
    public function test_responsive_project_grid(): void
    {
        $this->browse(function (Browser $browser) {
            // Desktop view
            $browser->visit('/projects')
                ->resize(1920, 1080)
                ->waitFor('[data-testid="project-cards-container"]', 10)
                ->assertPresent('[data-testid="project-cards-container"]')
                // Tablet view
                ->resize(768, 1024)
                ->pause(500)
                ->assertPresent('[data-testid="project-cards-container"]')
                // Mobile view
                ->resize(375, 812)
                ->pause(500)
                ->assertPresent('[data-testid="project-cards-container"]');
        });
    }

    /**
     * Create test data for the tests.
     */
    protected function createTestData(): void
    {
        // Create specific technologies needed for tests
        $laravelDescKey = TranslationKey::create(['key' => 'technology.laravel.description']);
        $vueDescKey = TranslationKey::create(['key' => 'technology.vue.description']);
        $phpDescKey = TranslationKey::create(['key' => 'technology.php.description']);
        $tailwindDescKey = TranslationKey::create(['key' => 'technology.tailwind.description']);
        $reactDescKey = TranslationKey::create(['key' => 'technology.react.description']);

        // Create icon pictures for technologies
        $laravelIcon = Picture::create([
            'filename' => 'laravel-icon.jpg',
            'width' => 128,
            'height' => 128,
            'size' => 256,
            'path_original' => 'uploads/laravel-icon.jpg',
        ]);
        $this->createOptimizedPictures($laravelIcon);

        $vueIcon = Picture::create([
            'filename' => 'vue-icon.jpg',
            'width' => 128,
            'height' => 128,
            'size' => 256,
            'path_original' => 'uploads/vue-icon.jpg',
        ]);
        $this->createOptimizedPictures($vueIcon);

        $phpIcon = Picture::create([
            'filename' => 'php-icon.jpg',
            'width' => 128,
            'height' => 128,
            'size' => 256,
            'path_original' => 'uploads/php-icon.jpg',
        ]);
        $this->createOptimizedPictures($phpIcon);

        $tailwindIcon = Picture::create([
            'filename' => 'tailwind-icon.jpg',
            'width' => 128,
            'height' => 128,
            'size' => 256,
            'path_original' => 'uploads/tailwind-icon.jpg',
        ]);
        $this->createOptimizedPictures($tailwindIcon);

        $reactIcon = Picture::create([
            'filename' => 'react-icon.jpg',
            'width' => 128,
            'height' => 128,
            'size' => 256,
            'path_original' => 'uploads/react-icon.jpg',
        ]);
        $this->createOptimizedPictures($reactIcon);

        // Create specific technologies
        $laravel = Technology::create([
            'name' => 'Laravel',
            'type' => TechnologyType::FRAMEWORK->value,
            'icon_picture_id' => $laravelIcon->id,
            'description_translation_key_id' => $laravelDescKey->id,
        ]);

        $vue = Technology::create([
            'name' => 'Vue.js',
            'type' => TechnologyType::FRAMEWORK->value,
            'icon_picture_id' => $vueIcon->id,
            'description_translation_key_id' => $vueDescKey->id,
        ]);

        $react = Technology::create([
            'name' => 'React',
            'type' => TechnologyType::FRAMEWORK->value,
            'icon_picture_id' => $reactIcon->id,
            'description_translation_key_id' => $reactDescKey->id,
        ]);

        $php = Technology::create([
            'name' => 'PHP',
            'type' => TechnologyType::LANGUAGE->value,
            'icon_picture_id' => $phpIcon->id,
            'description_translation_key_id' => $phpDescKey->id,
        ]);

        $tailwind = Technology::create([
            'name' => 'Tailwind CSS',
            'type' => TechnologyType::LIBRARY->value,
            'icon_picture_id' => $tailwindIcon->id,
            'description_translation_key_id' => $tailwindDescKey->id,
        ]);

        // Create websites with Laravel and Vue (without complete() to avoid duplicate technologies)
        $website1 = Creation::factory()
            ->create(['type' => CreationType::WEBSITE->value]);
        $website1->technologies()->attach([$laravel->id, $vue->id, $php->id]);

        $website2 = Creation::factory()
            ->create(['type' => CreationType::WEBSITE->value]);
        $website2->technologies()->attach([$laravel->id, $tailwind->id, $php->id]);

        // Create tool with React
        $tool = Creation::factory()
            ->create(['type' => CreationType::TOOL->value]);
        $tool->technologies()->attach([$react->id, $tailwind->id]);

        // Games creations
        Creation::factory()
            ->count(2)
            ->create(['type' => CreationType::GAME->value]);

        $techWithCreations = Technology::whereHas('creations')->count();
        $this->assertGreaterThan(0, $techWithCreations, 'No technologies have associated creations');
    }

    /**
     * Create optimized picture records for a picture.
     */
    protected function createOptimizedPictures(Picture $picture): void
    {
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
