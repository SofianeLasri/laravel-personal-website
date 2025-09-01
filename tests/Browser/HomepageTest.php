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
                ->pause(2000)  // Attendre le chargement
                ->assertSourceHas('Full-Stack'); // Vérifier dans le source
        });
    }

    /**
     * Test that the homepage shows the portfolio section.
     */
    public function test_homepage_displays_portfolio_section(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->pause(2000)  // Attendre le chargement
                ->assertSourceHas('Laravel')
                ->assertSourceHas('PHP');
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
                ->pause(2000)  // Attendre le chargement
                ->assertTitleContains('Projets'); // Vérifier que le titre contient "Projets"
        });
    }
}
