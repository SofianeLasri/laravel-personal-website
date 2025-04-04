<?php

namespace Tests\Feature\Models\Picture;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(OptimizedPicture::class)]
class OptimizedPictureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[Test]
    public function it_can_create_an_optimized_picture()
    {
        $picture = Picture::factory()->create();

        $optimizedPicture = OptimizedPicture::create([
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'path' => 'path/to/thumbnail.webp',
            'format' => 'webp',
        ]);

        $this->assertDatabaseHas('optimized_pictures', [
            'id' => $optimizedPicture->id,
            'picture_id' => $picture->id,
            'variant' => 'thumbnail',
            'path' => 'path/to/thumbnail.webp',
            'format' => 'webp',
        ]);
    }

    #[Test]
    public function it_belongs_to_a_picture()
    {
        $picture = Picture::factory()->create();
        $optimizedPicture = OptimizedPicture::factory()->create(['picture_id' => $picture->id]);

        $this->assertInstanceOf(Picture::class, $optimizedPicture->picture);
        $this->assertEquals($picture->id, $optimizedPicture->picture->id);
    }

    #[Test]
    public function it_defines_standard_sizes_as_constants()
    {
        $this->assertEquals(256, OptimizedPicture::THUMBNAIL_SIZE);
        $this->assertEquals(512, OptimizedPicture::SMALL_SIZE);
        $this->assertEquals(1024, OptimizedPicture::MEDIUM_SIZE);
        $this->assertEquals(2048, OptimizedPicture::LARGE_SIZE);
    }

    #[Test]
    public function it_defines_variant_options_as_constants()
    {
        $this->assertEquals(['thumbnail', 'small', 'medium', 'large', 'full'], OptimizedPicture::VARIANTS);
    }

    #[Test]
    public function it_defines_format_options_as_constants()
    {
        $this->assertEquals(['avif', 'webp'], OptimizedPicture::FORMATS);
    }

    #[Test]
    public function it_can_delete_a_file()
    {
        $path = 'optimized/test-image.webp';
        Storage::disk('public')->put($path, 'test content');

        $optimizedPicture = OptimizedPicture::factory()->create(['path' => $path]);

        $this->assertTrue(Storage::disk('public')->exists($path));

        $optimizedPicture->deleteFile();

        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    #[Test]
    public function it_doesnt_throw_error_when_deleting_nonexistent_file()
    {
        $nonExistentPath = 'does/not/exist.webp';
        $optimizedPicture = OptimizedPicture::factory()->create(['path' => $nonExistentPath]);

        try {
            $optimizedPicture->deleteFile();
            $this->assertTrue(true);
        } catch (Exception) {
            $this->fail('Exception was thrown when deleting non-existent file');
        }
    }

    #[Test]
    public function it_deletes_file_when_model_is_deleted()
    {
        $path = 'optimized/auto-delete-test.webp';
        Storage::disk('public')->put($path, 'test content');

        $optimizedPicture = OptimizedPicture::factory()->create(['path' => $path]);

        $this->assertTrue(Storage::disk('public')->exists($path));

        $optimizedPicture->delete();

        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    #[Test]
    public function it_can_handle_cdn_disk_not_configured()
    {
        $path = 'optimized/no-cdn-test.webp';
        Storage::disk('public')->put($path, 'test content');

        $optimizedPicture = OptimizedPicture::factory()->create(['path' => $path]);

        try {
            $optimizedPicture->deleteFile();
            $this->assertTrue(true);
        } catch (Exception) {
            $this->fail('Exception was thrown when CDN disk was not configured');
        }

        $this->assertFalse(Storage::disk('public')->exists($path));
    }
}
