<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\ProcessUserAgentsCommand;
use App\Jobs\ProcessUserAgentJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use Tests\TestCase;

#[CoversClass(ProcessUserAgentsCommand::class)]
class ProcessUserAgentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_job()
    {
        Queue::fake();
        UserAgent::factory()->count(10)->create();

        $this->artisan('process:user-agents');

        Queue::assertPushed(ProcessUserAgentJob::class, 10);
    }
}
