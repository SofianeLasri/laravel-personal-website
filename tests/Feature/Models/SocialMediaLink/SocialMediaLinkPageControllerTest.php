<?php

namespace Tests\Feature\Models\SocialMediaLink;

use App\Http\Controllers\Admin\SocialMediaLinkPageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(SocialMediaLinkPageController::class)]
class SocialMediaLinkPageControllerTest extends TestCase
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
        $response = $this->get(route('dashboard.social-media-links.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/social-links/List')
            ->has('socialMediaLinks')
        );
    }
}
