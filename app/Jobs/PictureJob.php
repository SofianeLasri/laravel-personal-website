<?php

namespace App\Jobs;

use App\Models\Picture;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PictureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    public function __construct(private readonly Picture $picture) {}

    public function handle(): void
    {
        try {
            Log::info('Starting picture optimization', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
                'attempt' => $this->attempts(),
            ]);

            $this->picture->optimize();

            // Check if optimization was successful
            if ($this->picture->hasInvalidOptimizedPictures()) {
                throw new \Exception('Optimization resulted in invalid files with 0 bytes');
            }

            Log::info('Picture optimization completed successfully', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
            ]);

        } catch (\App\Exceptions\ImageTranscodingException $e) {
            Log::error('Picture optimization job failed with transcoding error', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
                'error_code' => $e->getErrorCode()->value,
                'driver_used' => $e->getDriverUsed(),
                'fallback_attempted' => $e->getFallbackAttempted(),
                'attempt' => $this->attempts(),
                'error_details' => $e->toArray(),
            ]);

            // Handle specific error types differently
            $this->handleTranscodingError($e);

            // Re-throw the exception to mark job as failed
            throw $e;

        } catch (\Exception $e) {
            Log::error('Picture optimization job failed with general error', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString(),
            ]);

            // If this is the last attempt, send notification
            if ($this->attempts() >= $this->tries) {
                $notificationService = app(NotificationService::class);
                $notificationService->error(
                    'Échec définitif d\'optimisation d\'image',
                    'L\'optimisation de l\'image "' . $this->picture->filename . '" a échoué après ' . $this->tries . ' tentatives. Veuillez vérifier les logs ou réessayer manuellement.',
                    [
                        'picture_id' => $this->picture->id,
                        'filename' => $this->picture->filename,
                        'error' => $e->getMessage(),
                    ]
                );
            }

            // Re-throw the exception to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle specific transcoding errors with tailored responses
     */
    protected function handleTranscodingError(\App\Exceptions\ImageTranscodingException $e): void
    {
        $notificationService = app(NotificationService::class);

        // Handle different error types with appropriate severity and actions
        switch ($e->getErrorCode()) {
            case \App\Enums\ImageTranscodingError::ALL_DRIVERS_FAILED:
                if ($this->attempts() >= $this->tries) {
                    $notificationService->error(
                        'Tous les drivers d\'image ont échoué',
                        'Aucun driver (Imagick, GD) n\'a pu optimiser l\'image "' . $this->picture->filename . '". Vérifiez la configuration système.',
                        [
                            'picture_id' => $this->picture->id,
                            'error_details' => $e->toArray(),
                        ]
                    );
                }
                break;

            case \App\Enums\ImageTranscodingError::RESOURCE_LIMIT_EXCEEDED:
            case \App\Enums\ImageTranscodingError::MEMORY_LIMIT_EXCEEDED:
            case \App\Enums\ImageTranscodingError::IMAGE_TOO_LARGE:
                // Critical errors - notify immediately
                $notificationService->error(
                    'Limites système dépassées',
                    'L\'image "' . $this->picture->filename . '" dépasse les limites système: ' . $e->getMessage(),
                    [
                        'picture_id' => $this->picture->id,
                        'error_details' => $e->toArray(),
                        'action_required' => 'Réduire la taille de l\'image ou augmenter les limites système',
                    ]
                );
                break;

            case \App\Enums\ImageTranscodingError::IMAGICK_ENCODING_FAILED:
                if ($e->getFallbackAttempted()) {
                    // Fallback was attempted - less critical
                    if ($this->attempts() >= $this->tries) {
                        $notificationService->warning(
                            'Échec Imagick avec fallback tenté',
                            'Imagick a échoué pour l\'image "' . $this->picture->filename . '", fallback vers ' . $e->getFallbackAttempted() . ' également échoué.',
                            [
                                'picture_id' => $this->picture->id,
                                'error_details' => $e->toArray(),
                            ]
                        );
                    }
                } else {
                    // No fallback available - more critical
                    if ($this->attempts() >= $this->tries) {
                        $notificationService->error(
                            'Échec Imagick sans fallback',
                            'Imagick a échoué pour l\'image "' . $this->picture->filename . '" et aucun fallback n\'est disponible.',
                            [
                                'picture_id' => $this->picture->id,
                                'error_details' => $e->toArray(),
                            ]
                        );
                    }
                }
                break;

            case \App\Enums\ImageTranscodingError::UNSUPPORTED_FORMAT:
                $notificationService->warning(
                    'Format d\'image non supporté',
                    'Le format demandé n\'est pas supporté pour l\'image "' . $this->picture->filename . '": ' . $e->getMessage(),
                    [
                        'picture_id' => $this->picture->id,
                        'error_details' => $e->toArray(),
                    ]
                );
                break;

            default:
                // Other errors - standard handling
                if ($this->attempts() >= $this->tries) {
                    $notificationService->error(
                        'Erreur d\'optimisation d\'image',
                        'L\'optimisation de l\'image "' . $this->picture->filename . '" a échoué: ' . $e->getMessage(),
                        [
                            'picture_id' => $this->picture->id,
                            'error_details' => $e->toArray(),
                        ]
                    );
                }
                break;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Picture optimization job permanently failed', [
            'picture_id' => $this->picture->id,
            'filename' => $this->picture->filename,
            'exception' => $exception->getMessage(),
            'exception_type' => get_class($exception),
        ]);

        // Send a final failure notification if it's not a transcoding exception
        // (transcoding exceptions are handled in handleTranscodingError)
        if (!$exception instanceof \App\Exceptions\ImageTranscodingException) {
            $notificationService = app(NotificationService::class);
            $notificationService->error(
                'Échec définitif d\'optimisation d\'image',
                'L\'optimisation de l\'image "' . $this->picture->filename . '" a définitivement échoué: ' . $exception->getMessage(),
                [
                    'picture_id' => $this->picture->id,
                    'filename' => $this->picture->filename,
                    'exception' => $exception->getMessage(),
                    'exception_type' => get_class($exception),
                ]
            );
        }
    }
}
