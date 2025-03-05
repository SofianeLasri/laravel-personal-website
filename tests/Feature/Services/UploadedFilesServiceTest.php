<?php

namespace Tests\Feature\Services;

use App\Jobs\PictureJob;
use App\Models\Picture;
use App\Services\UploadedFilesService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UploadedFilesService::class)]
class UploadedFilesServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_it_stores_and_optimizes_an_uploaded_picture()
    {
        Storage::fake('public');
        Queue::fake();

        $uploadedFile = UploadedFile::fake()->image('test.jpg');

        $service = app(UploadedFilesService::class);
        $uploadedPicture = $service->storeAndOptimizeUploadedPicture($uploadedFile);

        Storage::disk('public')->assertExists($uploadedPicture->path_original);

        Queue::assertPushed(PictureJob::class);
    }

    #[Test]
    public function test_it_throws_exception_for_unsupported_format()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The specified file is not a picture or is not supported');

        $uploadedFile = UploadedFile::fake()->create('test.txt');

        $service = app(UploadedFilesService::class);
        $service->storeAndOptimizeUploadedPicture($uploadedFile);
    }

    #[Test]
    public function test_it_deletes_optimized_images()
    {
        Storage::fake('public');

        $picture = Picture::factory()->withOptimizedPictures()->create();

        $optimizedPictures = $picture->optimizedPictures;
        $optimizedPicturesPaths = [];

        foreach ($optimizedPictures as $optimizedPicture) {
            $optimizedPicturesPaths[] = $optimizedPicture->path;
        }

        $picture->deleteOptimized();

        Storage::disk('public')->assertMissing($optimizedPicturesPaths);
    }
}
