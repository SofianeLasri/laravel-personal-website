<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    /**
     * A basic browser test example.
     */
    public function test_basic_example(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertPathIs('/')
                ->waitForText('Laravel', 10) // Attendre que le contenu se charge
                ->assertSee('Laravel'); // Vérifier que Laravel est visible
        });
    }
}
