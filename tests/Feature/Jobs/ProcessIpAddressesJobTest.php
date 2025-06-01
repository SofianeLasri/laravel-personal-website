<?php

namespace Jobs;

use App\Jobs\ProcessIpAddressesJob;
use App\Services\IpAddressMetadataResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use Tests\TestCase;

#[CoversClass(ProcessIpAddressesJob::class)]
class ProcessIpAddressesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_ip_addresses_successfully()
    {
        $ip1 = IpAddress::factory()->create(['ip' => '208.80.152.201']);
        $ip2 = IpAddress::factory()->create(['ip' => '24.48.0.1']);

        Http::fake([
            '*' => Http::response([
                [
                    'status' => 'success',
                    'countryCode' => 'US',
                    'lat' => 37.7892,
                    'lon' => -122.402,
                    'query' => '208.80.152.201',
                ],
                [
                    'status' => 'success',
                    'countryCode' => 'CA',
                    'lat' => 45.6085,
                    'lon' => -73.5493,
                    'query' => '24.48.0.1',
                ],
            ]),
        ]);

        $job = new ProcessIpAddressesJob(collect([$ip1, $ip2]));
        $job->handle(app(IpAddressMetadataResolverService::class));

        $this->assertDatabaseHas('ip_address_metadata', [
            'ip_address_id' => $ip1->id,
            'country_code' => 'US',
            'lat' => 37.7892,
            'lon' => -122.402,
        ]);

        $this->assertDatabaseHas('ip_address_metadata', [
            'ip_address_id' => $ip2->id,
            'country_code' => 'CA',
            'lat' => 45.6085,
            'lon' => -73.5493,
        ]);
    }
}
