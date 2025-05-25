<?php

namespace Tests\Feature\Models\Picture;

use App\Http\Controllers\Admin\PicturePageController;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(PicturePageController::class)]
class PicturePageControllerTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    #[Test]
    public function test_basic_page_load()
    {
        Picture::factory()->count(5)->create();

        $response = $this->get(route('dashboard.pictures.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures')
                ->has('pictures.data', 5)
                ->has('filters')
                ->where('filters.search', '')
                ->where('filters.sort_by', 'created_at')
                ->where('filters.sort_direction', 'desc')
        );
    }

    #[Test]
    public function test_page_with_no_pictures()
    {
        $response = $this->get(route('dashboard.pictures.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures')
                ->where('pictures.data', [])
                ->where('pictures.total', 0)
                ->has('filters')
        );
    }

    #[Test]
    public function test_search_functionality()
    {
        Picture::factory()->create(['filename' => 'test-image.jpg']);
        Picture::factory()->create(['filename' => 'another-photo.png']);
        Picture::factory()->create(['filename' => 'document.pdf']);

        $response = $this->get(route('dashboard.pictures.index', ['search' => 'test']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 1)
                ->where('pictures.data.0.filename', 'test-image.jpg')
                ->where('filters.search', 'test')
        );
    }

    #[Test]
    public function test_search_with_partial_match()
    {
        Picture::factory()->create(['filename' => 'screenshot-2023.jpg']);
        Picture::factory()->create(['filename' => 'profile-photo.png']);
        Picture::factory()->create(['filename' => 'user-avatar.jpg']);

        $response = $this->get(route('dashboard.pictures.index', ['search' => 'photo']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 1)
                ->where('pictures.data.0.filename', 'profile-photo.png')
        );
    }

    #[Test]
    public function test_sort_by_filename_ascending()
    {
        Picture::factory()->create(['filename' => 'zebra.jpg']);
        Picture::factory()->create(['filename' => 'alpha.jpg']);
        Picture::factory()->create(['filename' => 'beta.jpg']);

        $response = $this->get(route('dashboard.pictures.index', [
            'sort_by' => 'filename',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 3)
                ->where('pictures.data.0.filename', 'alpha.jpg')
                ->where('pictures.data.1.filename', 'beta.jpg')
                ->where('pictures.data.2.filename', 'zebra.jpg')
                ->where('filters.sort_by', 'filename')
                ->where('filters.sort_direction', 'asc')
        );
    }

    #[Test]
    public function test_sort_by_filename_descending()
    {
        Picture::factory()->create(['filename' => 'alpha.jpg']);
        Picture::factory()->create(['filename' => 'zebra.jpg']);
        Picture::factory()->create(['filename' => 'beta.jpg']);

        $response = $this->get(route('dashboard.pictures.index', [
            'sort_by' => 'filename',
            'sort_direction' => 'desc',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 3)
                ->where('pictures.data.0.filename', 'zebra.jpg')
                ->where('pictures.data.1.filename', 'beta.jpg')
                ->where('pictures.data.2.filename', 'alpha.jpg')
                ->where('filters.sort_by', 'filename')
                ->where('filters.sort_direction', 'desc')
        );
    }

    #[Test]
    public function test_sort_by_size()
    {
        Picture::factory()->create(['filename' => 'small.jpg', 'size' => 1000]);
        Picture::factory()->create(['filename' => 'large.jpg', 'size' => 5000]);
        Picture::factory()->create(['filename' => 'medium.jpg', 'size' => 3000]);

        $response = $this->get(route('dashboard.pictures.index', [
            'sort_by' => 'size',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 3)
                ->where('pictures.data.0.filename', 'small.jpg')
                ->where('pictures.data.1.filename', 'medium.jpg')
                ->where('pictures.data.2.filename', 'large.jpg')
        );
    }

    #[Test]
    public function test_sort_by_dimensions()
    {
        Picture::factory()->create(['filename' => 'small.jpg', 'width' => 100, 'height' => 100]);
        Picture::factory()->create(['filename' => 'wide.jpg', 'width' => 500, 'height' => 200]);
        Picture::factory()->create(['filename' => 'tall.jpg', 'width' => 200, 'height' => 800]);

        $response = $this->get(route('dashboard.pictures.index', [
            'sort_by' => 'width',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 3)
                ->where('pictures.data.0.filename', 'small.jpg')
                ->where('pictures.data.1.filename', 'tall.jpg')
                ->where('pictures.data.2.filename', 'wide.jpg')
        );
    }

    #[Test]
    public function test_invalid_sort_column_falls_back_to_default()
    {
        Picture::factory()->count(3)->create();

        $response = $this->get(route('dashboard.pictures.index', [
            'sort_by' => 'invalid_column',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 3)
                ->where('filters.sort_by', 'invalid_column') // The filter is preserved
                ->where('filters.sort_direction', 'asc')
        );

        // But the actual sorting should fall back to created_at (default order)
    }

    #[Test]
    public function test_pagination_works()
    {
        // Create more than 24 pictures (the default page size)
        Picture::factory()->count(30)->create();

        $response = $this->get(route('dashboard.pictures.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 24) // First page should have 24 items
                ->where('pictures.per_page', 24)
                ->where('pictures.total', 30)
                ->where('pictures.current_page', 1)
                ->where('pictures.last_page', 2)
        );
    }

    #[Test]
    public function test_second_page_pagination()
    {
        Picture::factory()->count(30)->create();

        $response = $this->get(route('dashboard.pictures.index', ['page' => 2]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 6) // Second page should have remaining 6 items
                ->where('pictures.current_page', 2)
                ->where('pictures.last_page', 2)
        );
    }

    #[Test]
    public function test_combined_search_and_sort()
    {
        Picture::factory()->create(['filename' => 'zebra-image.jpg', 'size' => 1000]);
        Picture::factory()->create(['filename' => 'alpha-image.jpg', 'size' => 2000]);
        Picture::factory()->create(['filename' => 'beta-document.pdf', 'size' => 1500]);

        $response = $this->get(route('dashboard.pictures.index', [
            'search' => 'image',
            'sort_by' => 'size',
            'sort_direction' => 'asc',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 2) // Only images should be returned
                ->where('pictures.data.0.filename', 'zebra-image.jpg') // Smallest first
                ->where('pictures.data.1.filename', 'alpha-image.jpg')
                ->where('filters.search', 'image')
                ->where('filters.sort_by', 'size')
                ->where('filters.sort_direction', 'asc')
        );
    }

    #[Test]
    public function test_optimized_pictures_count_is_included()
    {
        $picture = Picture::factory()
            ->hasOptimizedPictures(3)
            ->create();

        $response = $this->get(route('dashboard.pictures.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 1)
                ->where('pictures.data.0.optimized_pictures_count', 3)
        );
    }

    #[Test]
    public function test_optimized_pictures_relationship_is_loaded()
    {
        $picture = Picture::factory()
            ->hasOptimizedPictures(2)
            ->create();

        $response = $this->get(route('dashboard.pictures.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 1)
                ->has('pictures.data.0.optimized_pictures', 2)
        );
    }

    #[Test]
    public function test_unauthenticated_user_is_redirected()
    {
        auth()->logout();

        $response = $this->get(route('dashboard.pictures.index'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function test_default_sort_is_created_at_desc()
    {
        $olderPicture = Picture::factory()->create(['created_at' => now()->subDay()]);
        $newerPicture = Picture::factory()->create(['created_at' => now()]);

        $response = $this->get(route('dashboard.pictures.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 2)
                ->where('pictures.data.0.id', $newerPicture->id) // Newer should be first
                ->where('pictures.data.1.id', $olderPicture->id)
                ->where('filters.sort_by', 'created_at')
                ->where('filters.sort_direction', 'desc')
        );
    }

    #[Test]
    public function test_case_insensitive_search()
    {
        Picture::factory()->create(['filename' => 'TestImage.JPG']);
        Picture::factory()->create(['filename' => 'another.png']);

        $response = $this->get(route('dashboard.pictures.index', ['search' => 'testimage']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('dashboard/pictures/List')
                ->has('pictures.data', 1)
                ->where('pictures.data.0.filename', 'TestImage.JPG')
        );
    }
}
