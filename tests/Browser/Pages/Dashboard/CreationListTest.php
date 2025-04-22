<?php

namespace Tests\Browser\Pages\Dashboard;

use App\Models\Creation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CreationListTest extends DuskTestCase
{
    use DatabaseMigrations, WithoutMiddleware;

    public function test_creation_data_is_displayed()
    {
        $creation = Creation::factory()->create();

        $this->browse(function (Browser $browser) use ($creation) {
            $browser->visit(route('dashboard.creations.index'))
                ->assertSee((string)$creation->id)
                ->assertSee($creation->name);
        });
    }
}
