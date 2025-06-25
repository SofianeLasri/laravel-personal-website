<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\OptimizePicturesCommand;
use App\Jobs\PictureJob;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OptimizePicturesCommand::class)]
class OptimizePicturesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_jobs_for_unoptimized_pictures(): void
    {
        Queue::fake();

        Picture::factory()->withOptimizedPictures()->create();

        $partiallyOptimizedPicture = Picture::factory()->create();

        $formats = OptimizedPicture::FORMATS;
        $variants = OptimizedPicture::VARIANTS;

        $count = 0;
        $maxCount = count($formats) * count($variants) - 1;

        foreach ($variants as $variant) {
            foreach ($formats as $format) {
                if ($count < $maxCount) {
                    OptimizedPicture::factory()->create([
                        'picture_id' => $partiallyOptimizedPicture->id,
                        'variant' => $variant,
                        'format' => $format,
                    ]);
                    $count++;
                }
            }
        }

        Picture::factory()->create();

        $this->artisan(OptimizePicturesCommand::class)
            ->expectsOutput('Starting to optimize pictures...')
            ->expectsOutput('All optimization jobs have been dispatched.')
            ->assertExitCode(0);
    }

    public function test_it_respects_chunk_size_option(): void
    {
        Queue::fake();

        Picture::factory()->count(25)->create();

        $this->artisan(OptimizePicturesCommand::class, ['--chunk' => 5])
            ->expectsOutput('Starting to optimize pictures...')
            ->expectsOutput('All optimization jobs have been dispatched.')
            ->assertExitCode(0);
    }

    public function test_it_reports_when_all_pictures_are_optimized(): void
    {
        Queue::fake();

        Picture::factory()->count(3)->withOptimizedPictures()->create();

        $this->artisan(OptimizePicturesCommand::class)
            ->expectsOutput('Starting to optimize pictures...')
            ->expectsOutput('All pictures are already optimized!')
            ->assertExitCode(0);

        Queue::assertNotPushed(PictureJob::class);
    }
}
