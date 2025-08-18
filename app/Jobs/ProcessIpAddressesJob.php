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
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;

class ProcessIpAddressesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  Collection<int, IpAddress>  $ipAddresses
     */
    public function __construct(private readonly Collection $ipAddresses) {}

    public function handle(IpAddressMetadataResolverService $ipAddressMetadataResolver): void
    {
        $filteredIpAddresses = $this->ipAddresses->filter(function ($ipAddress) {
            return ! $this->isLocalIp($ipAddress->ip);
        });

        if ($filteredIpAddresses->isEmpty()) {
            return;
        }

        try {
            $metadataObjects = $ipAddressMetadataResolver->resolve($filteredIpAddresses);
        } catch (Exception $exception) {
            report($exception);
            $this->fail($exception);

            return;
        }

        foreach ($metadataObjects as $metadata) {
            if ($metadata['status'] === 'fail') {
                Log::warning('Failed to resolve IP address metadata.', [
                    'query' => $metadata['query'],
                    'message' => $metadata['message'] ?? 'Unknown error',
                ]);

                continue;
            }

            // Only create metadata for successful responses with all required fields
            if (isset($metadata['countryCode'], $metadata['lat'], $metadata['lon'])) {
                $ipAddress = $this->ipAddresses->where('ip', $metadata['query'])->first();
                if ($ipAddress) {
                    IpAddressMetadata::create([
                        'ip_address_id' => $ipAddress->id,
                        'country_code' => $metadata['countryCode'],
                        'lat' => $metadata['lat'],
                        'lon' => $metadata['lon'],
                    ]);
                }
            }
        }
    }

    /**
     * Check if an IP address is local/private.
     *
     * @param  string  $ip  The IP address to check
     * @return bool True if the IP is local/private, false otherwise
     */
    private function isLocalIp(string $ip): bool
    {
        // Check for IPv6 addresses
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6 loopback
            if ($ip === '::1') {
                return true;
            }

            // IPv6 private addresses (fc00::/7)
            if (str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
                return true;
            }

            // IPv6 link-local (fe80::/10)
            if (str_starts_with($ip, 'fe80:')) {
                return true;
            }

            return false;
        }

        // Check for IPv4 addresses
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Use PHP's built-in filter for private and reserved ranges
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }
}
