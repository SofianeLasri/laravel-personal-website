<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUserAgentJob;
use Illuminate\Console\Command;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;

class ProcessUserAgentsCommand extends Command
{
    protected $signature = 'process:user-agents';

    protected $description = 'Process user agents to detect if they are bots';

    public function handle(): void
    {
        $userAgents = UserAgent::leftJoin('user_agent_metadata', 'user_agents.id', '=', 'user_agent_metadata.user_agent_id')
            ->whereNull('user_agent_metadata.id')
            ->select('user_agents.id', 'user_agents.user_agent')
            ->get();

        if ($userAgents->isEmpty()) {
            $this->info('No user agents to process.');

            return;
        }

        foreach ($userAgents as $userAgent) {
            ProcessUserAgentJob::dispatch($userAgent);
        }

        $this->info('Jobs dispatched for processing '.$userAgents->count().' user agents.');
    }
}
