<?php

namespace Tests\Feature\Pages\Public;

use App\Http\Controllers\Public\HomeController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(HomeController::class)]
class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_basic()
    {
        $response = $this->get(route('public.home'));

        $response->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('public/Home')
                    ->has('socialMediaLinks')
                    ->has('yearsOfExperience')
                    ->has('developmentCreationsCount')
                    ->has('masteredFrameworksCount')
                    ->has('laravelCreations')
                    ->has('technologyExperiences')
                    ->has('experiences')
            );
    }
}
