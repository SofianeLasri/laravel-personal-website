<?php

namespace Tests\Feature\Pages\Public;

use App\Http\Controllers\Public\ProjectsController;
use App\Models\Technology;
use App\Services\PublicControllersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProjectsController::class)]
class ProjectsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_basic()
    {
        $response = $this->get(route('projects'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('public/Projects')
            ->has('locale')
            ->has('translations.projects')
            ->has('socialMediaLinks')
            ->has('technologies')
        );
    }

    #[Test]
    public function test_technologies_structure()
    {
        $technologies = Technology::factory()->count(3)->create();

        $this->partialMock(PublicControllersService::class, function (MockInterface $mock) {
            $mock->shouldReceive('formatTechnologyForSSR')->andReturnUsing(function ($technology) {
                return [
                    'id' => $technology->id,
                    'creationCount' => 2,
                    'name' => $technology->name,
                    'icon' => $technology->icon,
                    'svgIcon' => $technology->svg_icon,
                ];
            });
        });

        $response = $this->get(route('projects'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('public/Projects')
            ->has('technologies', 3)
            ->where('technologies.0.id', $technologies[0]->id)
            ->where('technologies.0.creationCount', 2)
            ->where('technologies.0.name', $technologies[0]->name)
            ->where('technologies.0.icon', $technologies[0]->icon)
            ->where('technologies.0.svgIcon', $technologies[0]->svg_icon)
        );
    }
}
