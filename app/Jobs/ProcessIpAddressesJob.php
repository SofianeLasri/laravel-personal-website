<?php

namespace App\Jobs;

use App\Models\IpAddressMetadata;
use App\Services\IpAddressMetadataResolverService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessIpAddressesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly Collection $ipAddresses) {}

    public function handle(IpAddressMetadataResolverService $ipAddressMetadataResolver): void
    {
        try {
            $metadataObjects = $ipAddressMetadataResolver->resolve($this->ipAddresses);
        } catch (Exception $exception) {
            report($exception);
            $this->fail($exception);

            return;
        }

        foreach ($metadataObjects as $metadata) {
            if ($metadata['status'] === 'fail') {
                Log::warning('Failed to resolve IP address metadata.', [
                    'query' => $metadata['query'],
                    'message' => $metadata['message'],
                ]);

                continue;
            }

            IpAddressMetadata::create([
                'ip_address_id' => $this->ipAddresses->where('ip', $metadata['query'])->first()->id,
                'country_code' => $metadata['countryCode'],
                'lat' => $metadata['lat'],
                'lon' => $metadata['lon'],
            ]);
        }
    }
}
