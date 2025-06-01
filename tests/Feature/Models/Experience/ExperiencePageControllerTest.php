<?php

namespace Tests\Feature\Models\Experience;

use App\Http\Controllers\Admin\ExperiencePageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(ExperiencePageController::class)]
class ExperiencePageControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_list_page(): void
    {
        $response = $this->get(route('dashboard.experiences.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/experiences/List')
                ->has('experiences')
            );
    }

    #[Test]
    public function test_edit_page(): void
    {
        $response = $this->get(route('dashboard.experiences.edit', ['id' => 1]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/experiences/Edit')
                ->has('experience')
                ->has('technologies')
            );
    }
}
