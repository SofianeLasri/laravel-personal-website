<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('save:requests')->everyMinute();
Schedule::command('process:user-agents')->hourly();
Schedule::command('process:ip-adresses')->everyFiveMinutes();

Schedule::command('backup:clean --disable-notifications')->daily()->at('01:00');
Schedule::command('backup:run --disable-notifications')->daily()->at('01:30');
