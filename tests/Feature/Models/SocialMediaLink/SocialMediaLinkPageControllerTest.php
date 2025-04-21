<?php

namespace Tests\Feature\Models\SocialMediaLink;

use App\Http\Controllers\Admin\SocialMediaLinkPageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SocialMediaLinkPageController::class)]
class SocialMediaLinkPageControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    #[Test]
    public function test_basic()
    {
        $response = $this->get(route('dashboard.social-media-links.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/social-links/List')
            ->has('socialMediaLinks')
        );
    }
}
