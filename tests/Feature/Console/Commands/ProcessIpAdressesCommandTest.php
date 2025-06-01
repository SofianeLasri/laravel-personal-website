<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\ProcessIpAdressesCommand;
use App\Jobs\ProcessIpAddressesJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use Tests\TestCase;

#[CoversClass(ProcessIpAdressesCommand::class)]
class ProcessIpAdressesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_job()
    {
        Queue::fake();
        IpAddress::factory()->count(10)->create();

        Artisan::call('process:ip-adresses');

        Queue::assertPushed(ProcessIpAddressesJob::class);
    }
}
