<?php

namespace Tests\Feature\Models\Picture;

use App\Http\Controllers\Admin\Api\PictureController;
use App\Models\Picture;
use App\Services\UploadedFilesService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PictureController::class)]
class PictureControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    #[Test]
    public function test_index_with_pictures()
    {
        $pictures = Picture::factory()->count(3)->create();

        $response = $this->getJson(route('dashboard.api.pictures.index'));

        $response->assertOk()
            ->assertJson($pictures->toArray());
    }

    #[Test]
    public function test_index_without_pictures()
    {
        $response = $this->getJson(route('dashboard.api.pictures.index'));

        $response->assertOk()
            ->assertJson([]);
    }

    #[Test]
    public function test_create_picture()
    {
        $this->instance(
            UploadedFilesService::class,
            Mockery::mock(UploadedFilesService::class, function (MockInterface $mock) {
                $mock->shouldReceive('storeAndOptimizeUploadedPicture')->andReturn(Picture::factory()->make());
            })
        );

        $uploadedFile = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson(route('dashboard.api.pictures.store'), [
            'picture' => $uploadedFile,
        ]);

        $response->assertCreated();
    }

    #[Test]
    public function test_create_picture_but_service_throws_exception()
    {
        $this->instance(
            UploadedFilesService::class,
            Mockery::mock(UploadedFilesService::class, function (MockInterface $mock) {
                $mock->shouldReceive('storeAndOptimizeUploadedPicture')->andThrow(new Exception('Test exception'));
            })
        );

        $uploadedFile = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson(route('dashboard.api.pictures.store'), [
            'picture' => $uploadedFile,
        ]);

        $response->assertStatus(500)
            ->assertJson(['message' => 'Test exception']);
    }

    #[Test]
    public function test_create_validation()
    {
        $response = $this->postJson(route('dashboard.api.pictures.store'), [
            'picture' => 'not-an-uploaded-file',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('picture');
    }

    #[Test]
    public function test_show_picture()
    {
        $picture = Picture::factory()->create();

        $response = $this->getJson(route('dashboard.api.pictures.show', $picture));

        $response->assertOk()
            ->assertJson($picture->toArray());
    }

    #[Test]
    public function test_delete_picture()
    {
        $picture = Picture::factory()->create();

        $response = $this->deleteJson(route('dashboard.api.pictures.destroy', $picture));

        $response->assertNoContent();

        $this->assertDatabaseMissing('pictures', ['id' => $picture->id]);
    }
}
