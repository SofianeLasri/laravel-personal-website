<?php

namespace Tests\Feature\Models\Certification;

use App\Http\Controllers\Admin\Api\CertificationController;
use App\Models\Certification;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CertificationController::class)]
class CertificationControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    #[Test]
    public function test_index()
    {
        Certification::factory()->count(5)->create();

        $response = $this->getJson(route('dashboard.api.certifications.index'));

        $response->assertOk()
            ->assertJsonCount(5);
    }

    #[Test]
    public function test_store()
    {
        $picture = Picture::factory()->create();

        $data = [
            'name' => 'Laravel Certified Developer',
            'level' => 'Advanced',
            'score' => '850/1000',
            'date' => '2024-05-15',
            'link' => 'https://laravel.com/certification',
            'picture_id' => $picture->id,
        ];

        $response = $this->postJson(route('dashboard.api.certifications.store'), $data);

        $response->assertCreated()
            ->assertJsonPath('name', 'Laravel Certified Developer')
            ->assertJsonPath('level', 'Advanced')
            ->assertJsonPath('score', '850/1000')
            ->assertJsonPath('date', '2024-05-15T00:00:00.000000Z')
            ->assertJsonPath('link', 'https://laravel.com/certification')
            ->assertJsonPath('picture_id', $picture->id);

        $this->assertDatabaseHas('certifications', [
            'name' => 'Laravel Certified Developer',
            'level' => 'Advanced',
            'score' => '850/1000',
            'date' => '2024-05-15 00:00:00',
            'link' => 'https://laravel.com/certification',
            'picture_id' => $picture->id,
        ]);
    }

    #[Test]
    public function test_show()
    {
        $certification = Certification::factory()->create();

        $response = $this->getJson(route('dashboard.api.certifications.show', $certification->id));

        $response->assertOk();

        $data = $response->json();
        $this->assertEquals($certification->id, $data['id']);
        $this->assertEquals($certification->name, $data['name']);
        $this->assertEquals($certification->score, $data['score']);
        $this->assertEquals($certification->link, $data['link']);
        $this->assertEquals($certification->picture_id, $data['picture_id']);
    }

    #[Test]
    public function test_update()
    {
        $certification = Certification::factory()->create();
        $newPicture = Picture::factory()->create();

        $data = [
            'name' => 'Updated Laravel Certification',
            'level' => 'Expert',
            'score' => '950/1000',
            'date' => '2024-06-15',
            'link' => 'https://updated-laravel.com/certification',
            'picture_id' => $newPicture->id,
        ];

        $response = $this->putJson(route('dashboard.api.certifications.update', $certification), $data);

        $certification->refresh();

        $response->assertOk();

        $this->assertDatabaseHas('certifications', [
            'id' => $certification->id,
            'name' => 'Updated Laravel Certification',
            'level' => 'Expert',
            'score' => '950/1000',
            'date' => '2024-06-15 00:00:00',
            'link' => 'https://updated-laravel.com/certification',
            'picture_id' => $newPicture->id,
        ]);
    }

    #[Test]
    public function test_destroy()
    {
        $certification = Certification::factory()->create();
        $certificationId = $certification->id;

        $this->assertDatabaseHas('certifications', [
            'id' => $certificationId,
        ]);

        $response = $this->deleteJson(route('dashboard.api.certifications.destroy', $certification));

        $response->assertNoContent();

        // Check if record was actually deleted
        $this->assertNull(Certification::find($certificationId));
    }

    #[Test]
    public function test_store_validation_fails_with_invalid_data()
    {
        $data = [
            'name' => '',
            'level' => '',
            'score' => '',
            'date' => 'invalid-date',
            'link' => '',
            'picture_id' => 999999,
        ];

        $response = $this->postJson(route('dashboard.api.certifications.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'level', 'score', 'date', 'link', 'picture_id']);
    }

    #[Test]
    public function test_update_validation_fails_with_invalid_data()
    {
        $certification = Certification::factory()->create();

        $data = [
            'name' => '',
            'level' => '',
            'score' => '',
            'date' => 'invalid-date',
            'link' => '',
            'picture_id' => 999999,
        ];

        $response = $this->putJson(route('dashboard.api.certifications.update', $certification), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'level', 'score', 'date', 'link', 'picture_id']);
    }
}
