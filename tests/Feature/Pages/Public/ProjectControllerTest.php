<?php

namespace Tests\Feature\Pages\Public;

use App\Http\Controllers\Public\ProjectController;
use App\Models\Creation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProjectController::class)]
class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_basic()
    {
        $creation = Creation::factory()->create();
        $response = $this->get(route('public.projects.show', ['slug' => $creation->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('public/Project')
            ->has('locale')
            ->has('socialMediaLinks')
            ->has('creation')
            ->where('creation.slug', $creation->slug)
        );
    }
}
