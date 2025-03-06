<?php

namespace App\Providers;

use App\Services\ImageTranscodingService;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Imagick\Driver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageTranscodingService::class, function () {
            return new ImageTranscodingService(new Driver);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
