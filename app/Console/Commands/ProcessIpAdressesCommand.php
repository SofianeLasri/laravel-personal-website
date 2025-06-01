<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIpAddressesJob;
use Illuminate\Console\Command;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;

class ProcessIpAdressesCommand extends Command
{
    protected $signature = 'process:ip-adresses';

    protected $description = 'Process ip adresses to resolve their location';

    public function handle(): void
    {
        $ipAddresses = IpAddress::leftJoin('ip_address_metadata', 'ip_addresses.id', '=', 'ip_address_metadata.ip_address_id')
            ->whereNull('ip_address_metadata.id')
            ->select('ip_addresses.id', 'ip_addresses.ip')
            ->get();

        if ($ipAddresses->isEmpty()) {
            $this->info('No ip adresses to process');

            return;
        }

        $this->info("Processing {$ipAddresses->count()} ip adresses");
        ProcessIpAddressesJob::dispatch($ipAddresses);
    }
}
