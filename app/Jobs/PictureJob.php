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
            $this->picture->optimize();

            // Check if optimization was successful
            if ($this->picture->hasInvalidOptimizedPictures()) {
                throw new \Exception('Optimization resulted in invalid files with 0 bytes');
            }
        } catch (\Exception $e) {
            Log::error('Picture optimization job failed', [
                'picture_id' => $this->picture->id,
                'filename' => $this->picture->filename,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
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
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Picture optimization job permanently failed', [
            'picture_id' => $this->picture->id,
            'filename' => $this->picture->filename,
            'exception' => $exception->getMessage(),
        ]);
    }
}
