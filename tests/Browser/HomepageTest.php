<?php

namespace Tests\Browser;

use App\Models\Creation;
use App\Models\Technology;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class HomepageTest extends DuskTestCase
{
    /**
     * Test that the homepage loads successfully.
     */
    public function test_homepage_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertPathIs('/')
                ->assertPresent('body');
        });
    }

    /**
     * Test that creations are displayed on the homepage with factory data.
     */
    public function test_homepage_displays_creations_from_factory(): void
    {
        // Create test data using factories
        $technology = Technology::factory()->create([
            'name' => 'Laravel Test',
            'type' => 'backend',
        ]);

        $creation = Creation::factory()
            ->published()
            ->create([
                'title' => 'Test Project E2E',
                'slug' => 'test-project-e2e',
            ]);

        $creation->technologies()->attach($technology);

        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitForText('Test Project E2E', 10)
                ->assertSee('Test Project E2E');
        });
    }

    /**
     * Test navigation to project detail page.
     */
    public function test_navigation_to_project_detail(): void
    {
        $creation = Creation::factory()
            ->published()
            ->create([
                'title' => 'Navigable Project',
                'slug' => 'navigable-project',
            ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->clickLink('Navigable Project')
                ->assertPathIs('/projects/navigable-project')
                ->assertSee('Navigable Project');
        });
    }
}
