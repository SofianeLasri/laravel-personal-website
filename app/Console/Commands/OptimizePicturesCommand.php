<?php

namespace App\Console\Commands;

use App\Jobs\PictureJob;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizePicturesCommand extends Command
{
    protected $signature = 'optimize:pictures {--chunk=10 : Number of pictures to process at once}';

    protected $description = 'Generate optimized versions for pictures that don\'t have them';

    public function handle(): void
    {
        $this->info('Starting to optimize pictures...');

        $expectedOptimizedCount = count(OptimizedPicture::VARIANTS) * count(OptimizedPicture::FORMATS);

        $pictureIdsToOptimize = DB::table('pictures')
            ->leftJoin('optimized_pictures', 'pictures.id', '=', 'optimized_pictures.picture_id')
            ->select('pictures.id')
            ->groupBy('pictures.id')
            ->havingRaw('COUNT(optimized_pictures.id) < ?', [$expectedOptimizedCount])
            ->pluck('id');

        $totalCount = $pictureIdsToOptimize->count();

        if ($totalCount === 0) {
            $this->info('All pictures are already optimized!');
            return;
        }

        $this->info("Found {$totalCount} pictures to optimize.");

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $chunkSize = max(1, (int)$this->option('chunk'));

        $pictureIdsToOptimize->chunk($chunkSize)->each(function ($ids) use ($bar) {
            foreach ($ids as $id) {
                $picture = Picture::find($id);
                if ($picture) {
                    PictureJob::dispatch($picture);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('All optimization jobs have been dispatched.');
    }
}
