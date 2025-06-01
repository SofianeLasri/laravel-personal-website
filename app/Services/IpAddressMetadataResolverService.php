<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;

class IpAddressMetadataResolverService
{
    /**
     * Resolve metadata for the given IP addresses.
     * This method has a fail tolerance. The only exception that is thrown is when the API server returns a 422 status code.
     * Server errors are logged and the method returns without throwing an exception.
     *
     * @param  Collection  $ipAddresses  The IP addresses to resolve metadata for. Instances of IpAddress.
     * @return array{array{status: 'success'|'fail', message?: string, countryCode?: string, lat?: float, lon?: float, query: string}} The resolved metadata for each IP address.
     *
     * @throws ConnectionException
     */
    public static function resolve(Collection $ipAddresses): array
    {
        $url = config('services.ip-address-resolver.url').'?fields=status,message,countryCode,lat,lon,query';
        $maxIpPerCall = config('services.ip-address-resolver.max_ip_addresses_per_call');
        $maxCallsPerMinute = config('services.ip-address-resolver.call_limit_per_minute');
        $currentCallsCount = Cache::get('services.ip-address-resolver.calls_count', 0);

        if ($currentCallsCount >= $maxCallsPerMinute) {
            Log::info('Max calls per minute reached. Skipping metadata resolution.');

            return [];
        }

        Cache::increment('services.ip-address-resolver.calls_count', 1, 60);

        if ($ipAddresses->count() > $maxIpPerCall) {
            $ipAddresses = $ipAddresses->take($maxIpPerCall);
        }

        $response = Http::post($url, $ipAddresses->pluck('ip')->toArray());

        if ($response->failed()) {
            $returnedError = [
                'status' => $response->status(),
                'message' => $response->body(),
            ];

            if ($response->unprocessableContent()) {
                $apiResponse = $response->json();
                Log::error('The API rejected the request with a 422 unprocessable entity status code. ', $returnedError);
                throw new Exception('The API rejected the request with a 422 unprocessable entity status code. '.$apiResponse['message']);
            }
            if ($response->serverError()) {
                Log::info('The API server encountered an error while processing the request. Skipping metadata resolution.', $returnedError);

                return [];
            }
            Log::error('The API server returned an unexpected status code. Skipping metadata resolution.', $returnedError);

            return [];
        }

        return $response->json();
    }
}
