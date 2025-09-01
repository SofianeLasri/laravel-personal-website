<?php

namespace Tests\Browser;

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
                ->waitFor('#app', 5)  // Plus de temps pour le CI
                ->assertSee('Full-Stack');
        });
    }

    /**
     * Test that the homepage shows the portfolio section.
     */
    public function test_homepage_displays_portfolio_section(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitFor('#app', 5)  // Plus de temps pour le CI
                ->assertSee('Laravel')
                ->assertSee('PHP');
        });
    }

    /**
     * Test that we can navigate to projects page.
     */
    public function test_navigation_to_projects_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/projects')
                ->assertPathIs('/projects')
                ->waitFor('#app', 5)  // Plus de temps pour le CI
                ->assertSee('Projets');
        });
    }
}
