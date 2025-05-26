<?php

namespace Tests\Feature\Models\Certification;

use App\Http\Controllers\Admin\CertificationPageController;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CertificationPageController::class)]
class CertificationPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    #[Test]
    public function test_list_page()
    {
        $response = $this->get(route('dashboard.certifications.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/certifications/List')
                ->has('certifications')
        );
    }

    #[Test]
    public function test_create_page()
    {
        $response = $this->get(route('dashboard.certifications.create'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/certifications/Edit')
                ->where('certification', null)
                ->has('pictures')
        );
    }

    #[Test]
    public function test_edit_page_with_parameters()
    {
        $certification = Certification::factory()->create();

        $response = $this->get(route('dashboard.certifications.edit', ['id' => $certification->id]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/certifications/Edit')
                ->where('certification.id', $certification->id)
                ->has('pictures')
        );
    }
}
