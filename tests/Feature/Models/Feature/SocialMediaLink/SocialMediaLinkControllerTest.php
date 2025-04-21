<?php

namespace Tests\Feature\Models\Feature\SocialMediaLink;

use App\Http\Controllers\Admin\Api\SocialMediaLinkController;
use App\Models\SocialMediaLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SocialMediaLinkController::class)]
class SocialMediaLinkControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    #[Test]
    public function test_index()
    {
        SocialMediaLink::factory()->count(5)->create();
        $response = $this->get(route('dashboard.api.social-media-links.index'));

        $response->assertOk();
        $response->assertJsonCount(5);
    }

    #[Test]
    public function test_store()
    {
        $data = [
            'name' => 'Test Link',
            'url' => 'https://example.com',
            'icon_svg' => 'test-icon',
        ];

        $response = $this->post(route('dashboard.api.social-media-links.store'), $data);

        $response->assertCreated();
        $this->assertDatabaseHas('social_media_links', $data);
    }

    #[Test]
    public function test_show()
    {
        $socialMediaLink = SocialMediaLink::factory()->create();

        $response = $this->get(route('dashboard.api.social-media-links.show', $socialMediaLink));

        $response->assertOk();
        $response->assertJson($socialMediaLink->toArray());
    }

    #[Test]
    public function test_show_return_404_with_invalid_id()
    {
        $response = $this->get(route('dashboard.api.social-media-links.show', 999));

        $response->assertNotFound();
    }

    #[Test]
    public function test_update()
    {
        $socialMediaLink = SocialMediaLink::factory()->create();

        $data = [
            'name' => 'Updated Link',
            'url' => 'https://updated-example.com',
            'icon_svg' => 'updated-icon',
        ];

        $response = $this->put(route('dashboard.api.social-media-links.update', $socialMediaLink), $data);

        $response->assertOk();
        $this->assertDatabaseHas('social_media_links', $data);
    }

    #[Test]
    public function test_destroy()
    {
        $socialMediaLink = SocialMediaLink::factory()->create();

        $response = $this->delete(route('dashboard.api.social-media-links.destroy', $socialMediaLink));

        $response->assertNoContent();
        $this->assertDatabaseMissing('social_media_links', $socialMediaLink->toArray());
    }
}
