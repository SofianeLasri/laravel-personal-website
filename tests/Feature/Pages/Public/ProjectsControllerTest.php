<?php

namespace Tests\Feature\Pages\Public;

use App\Http\Controllers\Public\ProjectsController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ProjectsController::class)]
class ProjectsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic()
    {
        $response = $this->get(route('projects')); //

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('public/Projects')
            ->has('locale')
            ->has('translations.projects')
            ->has('socialMediaLinks')
        );
    }
}
