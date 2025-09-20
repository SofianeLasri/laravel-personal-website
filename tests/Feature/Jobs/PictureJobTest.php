<?php

namespace Tests\Feature\Jobs;

use App\Enums\ImageTranscodingError;
use App\Exceptions\ImageTranscodingException;
use App\Jobs\PictureJob;
use App\Models\Picture;
use App\Services\NotificationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PictureJob::class)]
class PictureJobTest extends TestCase
{
    use RefreshDatabase;

    private Picture $picture;

    private NotificationService $mockNotificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->picture = Picture::factory()->create([
            'filename' => 'test-image.jpg',
            'path_original' => 'uploads/test-image.jpg',
        ]);

        $this->mockNotificationService = Mockery::mock(NotificationService::class);
        $this->app->instance(NotificationService::class, $this->mockNotificationService);

        // Mock Log facade to avoid unexpected calls
        Log::spy();
    }

    #[Test]
    public function it_successfully_processes_picture_optimization()
    {
        // Mock the picture to simulate successful optimization
        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andReturnNull();

        $pictureMock->shouldReceive('hasInvalidOptimizedPictures')
            ->once()
            ->andReturnFalse();

        Log::shouldReceive('info')
            ->with('Starting picture optimization', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
                'attempt' => 1,
            ])
            ->once();

        Log::shouldReceive('info')
            ->with('Picture optimization completed successfully', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
            ])
            ->once();

        $job = new PictureJob($pictureMock);
        $job->handle();
    }

    #[Test]
    public function it_throws_exception_when_optimization_results_in_invalid_files()
    {
        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andReturnNull();

        $pictureMock->shouldReceive('hasInvalidOptimizedPictures')
            ->once()
            ->andReturnTrue();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Optimization resulted in invalid files with 0 bytes');

        $job = new PictureJob($pictureMock);
        $job->handle();
    }

    #[Test]
    public function it_handles_all_drivers_failed_transcoding_error_on_last_attempt()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::ALL_DRIVERS_FAILED,
            'imagick',
            'All image drivers failed',
            null,
            ['test' => 'context']
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 3 attempts (last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(3);

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Tous les drivers d\'image ont échoué',
                'Aucun driver (Imagick, GD) n\'a pu optimiser l\'image "test-image.jpg". Vérifiez la configuration système.',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_all_drivers_failed_transcoding_error_before_last_attempt()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::ALL_DRIVERS_FAILED,
            'imagick',
            'All image drivers failed'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 1 attempt (not last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(1);

        // Should not send notification before last attempt
        $this->mockNotificationService->shouldNotReceive('error');

        $this->expectException(ImageTranscodingException::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_resource_limit_exceeded_error_immediately()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::RESOURCE_LIMIT_EXCEEDED,
            'imagick',
            'Resource limit exceeded'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Limites système dépassées',
                'L\'image "test-image.jpg" dépasse les limites système: Resource limit exceeded (Driver: imagick)',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                    'action_required' => 'Réduire la taille de l\'image ou augmenter les limites système',
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $job = new PictureJob($pictureMock);
        $job->handle();
    }

    #[Test]
    public function it_handles_memory_limit_exceeded_error_immediately()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::MEMORY_LIMIT_EXCEEDED,
            'imagick',
            'Memory limit exceeded'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Limites système dépassées',
                'L\'image "test-image.jpg" dépasse les limites système: Memory limit exceeded (Driver: imagick)',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                    'action_required' => 'Réduire la taille de l\'image ou augmenter les limites système',
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $job = new PictureJob($pictureMock);
        $job->handle();
    }

    #[Test]
    public function it_handles_image_too_large_error_immediately()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::IMAGE_TOO_LARGE,
            'imagick',
            'Image too large'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Limites système dépassées',
                'L\'image "test-image.jpg" dépasse les limites système: Image too large (Driver: imagick)',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                    'action_required' => 'Réduire la taille de l\'image ou augmenter les limites système',
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $job = new PictureJob($pictureMock);
        $job->handle();
    }

    #[Test]
    public function it_handles_imagick_encoding_failed_with_fallback_attempted_on_last_attempt()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::IMAGICK_ENCODING_FAILED,
            'imagick',
            'Imagick encoding failed',
            'gd'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 3 attempts (last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(3);

        $this->mockNotificationService->shouldReceive('warning')
            ->with(
                'Échec Imagick avec fallback tenté',
                'Imagick a échoué pour l\'image "test-image.jpg", fallback vers gd également échoué.',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_imagick_encoding_failed_without_fallback_on_last_attempt()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::IMAGICK_ENCODING_FAILED,
            'imagick',
            'Imagick encoding failed',
            null
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 3 attempts (last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(3);

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Échec Imagick sans fallback',
                'Imagick a échoué pour l\'image "test-image.jpg" et aucun fallback n\'est disponible.',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_imagick_encoding_failed_before_last_attempt()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::IMAGICK_ENCODING_FAILED,
            'imagick',
            'Imagick encoding failed'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 1 attempt (not last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(1);

        // Should not send notification before last attempt
        $this->mockNotificationService->shouldNotReceive('warning');
        $this->mockNotificationService->shouldNotReceive('error');

        $this->expectException(ImageTranscodingException::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_unsupported_format_error_immediately()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::UNSUPPORTED_FORMAT,
            'imagick',
            'Unsupported format'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        $this->mockNotificationService->shouldReceive('warning')
            ->with(
                'Format d\'image non supporté',
                'Le format demandé n\'est pas supporté pour l\'image "test-image.jpg": Unsupported format (Driver: imagick)',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $job = new PictureJob($pictureMock);
        $job->handle();
    }

    #[Test]
    public function it_handles_other_transcoding_errors_on_last_attempt()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::GD_ENCODING_FAILED,
            'gd',
            'GD encoding failed'
        );

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 3 attempts (last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(3);

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Erreur d\'optimisation d\'image',
                'L\'optimisation de l\'image "test-image.jpg" a échoué: GD encoding failed (Driver: gd)',
                [
                    'picture_id' => $this->picture->id,
                    'error_details' => $exception->toArray(),
                ]
            )
            ->once();

        $this->expectException(ImageTranscodingException::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_general_exception_on_last_attempt()
    {
        $exception = new Exception('General error occurred');

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 3 attempts (last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(3);
        $jobMock->tries = 3;

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Échec définitif d\'optimisation d\'image',
                'L\'optimisation de l\'image "test-image.jpg" a échoué après 3 tentatives. Veuillez vérifier les logs ou réessayer manuellement.',
                [
                    'picture_id' => $this->picture->id,
                    'filename' => $this->picture->filename,
                    'error' => 'General error occurred',
                ]
            )
            ->once();

        $this->expectException(Exception::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_general_exception_before_last_attempt()
    {
        $exception = new Exception('General error occurred');

        $pictureMock = Mockery::mock(Picture::class)->makePartial();
        $pictureMock->id = $this->picture->id;
        $pictureMock->filename = $this->picture->filename;

        $pictureMock->shouldReceive('optimize')
            ->once()
            ->andThrow($exception);

        // Create job mock that returns 1 attempt (not last attempt)
        $jobMock = Mockery::mock(PictureJob::class, [$pictureMock])->makePartial();
        $jobMock->shouldReceive('attempts')->andReturn(1);
        $jobMock->tries = 3;

        // Should not send notification before last attempt
        $this->mockNotificationService->shouldNotReceive('error');

        $this->expectException(Exception::class);

        $jobMock->handle();
    }

    #[Test]
    public function it_handles_failed_method_with_transcoding_exception()
    {
        $exception = new ImageTranscodingException(
            ImageTranscodingError::ALL_DRIVERS_FAILED,
            'imagick',
            'All drivers failed'
        );

        Log::shouldReceive('error')
            ->with('Picture optimization job permanently failed', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
                'exception' => 'All drivers failed (Driver: imagick)',
                'exception_type' => ImageTranscodingException::class,
            ])
            ->once();

        // Should not send notification for transcoding exceptions
        $this->mockNotificationService->shouldNotReceive('error');

        $job = new PictureJob($this->picture);
        $job->failed($exception);
    }

    #[Test]
    public function it_handles_failed_method_with_general_exception()
    {
        $exception = new Exception('General failure');

        Log::shouldReceive('error')
            ->with('Picture optimization job permanently failed', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
                'exception' => 'General failure',
                'exception_type' => Exception::class,
            ])
            ->once();

        $this->mockNotificationService->shouldReceive('error')
            ->with(
                'Échec définitif d\'optimisation d\'image',
                'L\'optimisation de l\'image "test-image.jpg" a définitivement échoué: General failure',
                [
                    'picture_id' => $this->picture->id,
                    'filename' => $this->picture->filename,
                    'exception' => 'General failure',
                    'exception_type' => Exception::class,
                ]
            )
            ->once();

        $job = new PictureJob($this->picture);
        $job->failed($exception);
    }

    #[Test]
    public function it_has_correct_job_properties()
    {
        $job = new PictureJob($this->picture);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }
}
