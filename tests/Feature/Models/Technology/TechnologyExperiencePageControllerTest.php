<?php

namespace Tests\Feature\Models\Technology;

use App\Http\Controllers\Admin\TechnologyExperiencePageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(TechnologyExperiencePageController::class)]
class TechnologyExperiencePageControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_basic()
    {
        $response = $this->get(route('dashboard.technology-experiences.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/technology-experiences/List')
            ->has('technologies')
            ->has('technologyExperiences')
        );
    }
}
