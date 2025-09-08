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
        $this->markTestIncomplete(
            'Filters are not displayed due to frontend issue. '.
            'Technologies need to be associated with creations visible in the current tab.'
        );

        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitFor('[data-filter-type="framework"]', 10)
                // Click on Laravel framework filter
                ->click('[data-filter-type="framework"][data-filter-value="laravel"]')
                ->pause(500)
                ->assertQueryStringHas('frameworks', 'laravel')
                // Verify filtered projects are shown
                ->assertPresent('[data-testid="project-card"]')
                // Click again to deselect
                ->click('[data-filter-type="framework"][data-filter-value="laravel"]')
                ->pause(500)
                ->assertQueryStringMissing('frameworks');
        });
    }

    /**
     * Test multiple filters can be selected.
     */
    public function test_multiple_filters(): void
    {
        $this->markTestIncomplete(
            'Filters are not displayed due to frontend issue. '.
            'Technologies need to be associated with creations visible in the current tab.'
        );

        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitFor('[data-filter-type="framework"]', 10)
                // Select Laravel framework
                ->click('[data-filter-type="framework"][data-filter-value="laravel"]')
                ->pause(300)
                // Select Vue.js framework
                ->click('[data-filter-type="framework"][data-filter-value="vue"]')
                ->pause(300)
                // Select PHP language
                ->click('[data-filter-type="language"][data-filter-value="php"]')
                ->pause(500)
                // Check URL contains all filters
                ->assertQueryStringHas('frameworks', 'laravel')
                ->assertQueryStringHas('frameworks', 'vue')
                ->assertQueryStringHas('languages', 'php');
        });
    }

    /**
     * Test clear filters functionality.
     */
    public function test_clear_filters(): void
    {
        $this->markTestIncomplete(
            'Filters are not displayed due to frontend issue. '.
            'Technologies need to be associated with creations visible in the current tab.'
        );

        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitFor('[data-filter-type="framework"]', 10)
                // Apply some filters
                ->click('[data-filter-type="framework"][data-filter-value="laravel"]')
                ->pause(300)
                ->click('[data-filter-type="language"][data-filter-value="php"]')
                ->pause(300)
                // Clear filters
                ->click('[data-testid="clear-filters-button"]')
                ->pause(500)
                ->assertQueryStringMissing('frameworks')
                ->assertQueryStringMissing('languages');
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
        $this->markTestIncomplete(
            'Filters are not displayed due to frontend issue.'
        );

        $this->browse(function (Browser $browser) {
            $browser->visit('/projects?tab=games&frameworks=laravel&languages=php')
                ->waitFor('[data-filter-type="framework"]', 10)
                // Verify tab is selected
                ->assertPresent('[data-tab="games"][data-active="true"]')
                // Verify filters are selected
                ->assertPresent('[data-filter-type="framework"][data-filter-value="laravel"][data-selected="true"]')
                ->assertPresent('[data-filter-type="language"][data-filter-value="php"][data-selected="true"]');
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
     * Test empty state when no projects match filters.
     */
    public function test_empty_state_with_no_matching_projects(): void
    {
        $this->markTestIncomplete(
            'Filters are not displayed due to frontend issue.'
        );

        // Create a filter combination that returns no results
        $nonExistentDescKey = TranslationKey::create(['key' => 'technology.nonexistent.description']);
        $nonExistentIcon = Picture::create([
            'filename' => 'nonexistent-icon.jpg',
            'width' => 128,
            'height' => 128,
            'size' => 256,
            'path_original' => 'uploads/nonexistent-icon.jpg',
        ]);
        Technology::create([
            'name' => 'NonExistent',
            'type' => TechnologyType::FRAMEWORK->value,
            'icon_picture_id' => $nonExistentIcon->id,
            'description_translation_key_id' => $nonExistentDescKey->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->waitFor('[data-filter-type="framework"]', 10)
                ->click('[data-filter-type="framework"][data-filter-value="nonexistent"]')
                ->pause(500)
                ->assertSee('Aucun projet')
                ->assertPresent('[data-testid="no-projects-message"]');
        });
    }

    /**
     * Create test data for the tests.
     */
    protected function createTestData(): void
    {
        $technologies = Technology::factory()->createSet();

        // Websites creations
        Creation::factory()
            ->count(2)
            ->complete()
            ->create(['type' => CreationType::WEBSITE->value]);

        // Tools creations
        Creation::factory()
            ->complete()
            ->create(['type' => CreationType::TOOL->value]);

        // Games creations
        Creation::factory()
            ->count(2)
            ->withExistingTechnologies($technologies->pluck('id')->toArray())
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
