<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessIpAddressesJob;
use App\Models\IpAddressMetadata;
use App\Services\IpAddressMetadataResolverService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
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

    public function test_handles_failed_ip_resolution()
    {

        $ip1 = IpAddress::factory()->create(['ip' => '208.80.152.201']);
        $ip2 = IpAddress::factory()->create(['ip' => '24.48.0.1']);

        $mockResolver = Mockery::mock(IpAddressMetadataResolverService::class);
        $mockResolver->shouldReceive('resolve')
            ->andReturn([
                [
                    'status' => 'fail',
                    'message' => 'Invalid IP address',
                    'query' => '208.80.152.201',
                ],
                [
                    'status' => 'success',
                    'countryCode' => 'CA',
                    'lat' => 45.6085,
                    'lon' => -73.5493,
                    'query' => '24.48.0.1',
                ],
            ]);

        $job = new ProcessIpAddressesJob(collect([$ip1, $ip2]));
        $job->handle($mockResolver);

        // Should not create metadata for failed IP
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $ip1->id,
        ]);

        // Should create metadata for successful IP
        $this->assertDatabaseHas('ip_address_metadata', [
            'ip_address_id' => $ip2->id,
            'country_code' => 'CA',
            'lat' => 45.6085,
            'lon' => -73.5493,
        ]);
    }

    public function test_handles_resolver_service_exception()
    {
        $ip1 = IpAddress::factory()->create(['ip' => '208.80.152.201']);
        $exception = new Exception('Service unavailable');

        $mockResolver = Mockery::mock(IpAddressMetadataResolverService::class);
        $mockResolver->shouldReceive('resolve')
            ->andThrow($exception);

        $job = new ProcessIpAddressesJob(collect([$ip1]));

        // The job should handle the exception and not re-throw it
        $job->handle($mockResolver);

        // Should not create any metadata when exception occurs
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $ip1->id,
        ]);
    }

    public function test_creates_metadata_records_correctly()
    {
        $ip1 = IpAddress::factory()->create(['ip' => '8.8.8.8']);
        $ip2 = IpAddress::factory()->create(['ip' => '1.1.1.1']);

        $mockResolver = Mockery::mock(IpAddressMetadataResolverService::class);
        $mockResolver->shouldReceive('resolve')
            ->andReturn([
                [
                    'status' => 'success',
                    'countryCode' => 'US',
                    'lat' => 37.751,
                    'lon' => -97.822,
                    'query' => '8.8.8.8',
                ],
                [
                    'status' => 'success',
                    'countryCode' => 'AU',
                    'lat' => -33.494,
                    'lon' => 143.2104,
                    'query' => '1.1.1.1',
                ],
            ]);

        $job = new ProcessIpAddressesJob(collect([$ip1, $ip2]));
        $job->handle($mockResolver);

        $metadata1 = IpAddressMetadata::where('ip_address_id', $ip1->id)->first();
        $metadata2 = IpAddressMetadata::where('ip_address_id', $ip2->id)->first();

        $this->assertNotNull($metadata1);
        $this->assertNotNull($metadata2);

        $this->assertEquals('US', $metadata1->country_code);
        $this->assertEquals(37.751, $metadata1->lat);
        $this->assertEquals(-97.822, $metadata1->lon);

        $this->assertEquals('AU', $metadata2->country_code);
        $this->assertEquals(-33.494, $metadata2->lat);
        $this->assertEquals(143.2104, $metadata2->lon);
    }

    public function test_filters_out_local_ip_addresses()
    {
        // Create a mix of local and public IPs
        $localIp1 = IpAddress::factory()->create(['ip' => '127.0.0.1']);
        $localIp2 = IpAddress::factory()->create(['ip' => '192.168.1.1']);
        $localIp3 = IpAddress::factory()->create(['ip' => '10.0.0.1']);
        $localIp4 = IpAddress::factory()->create(['ip' => '172.16.0.1']);
        $localIp5 = IpAddress::factory()->create(['ip' => '::1']);
        $publicIp = IpAddress::factory()->create(['ip' => '8.8.8.8']);

        $mockResolver = Mockery::mock(IpAddressMetadataResolverService::class);
        // Should only be called with the public IP
        $mockResolver->shouldReceive('resolve')
            ->once()
            ->with(Mockery::on(function ($collection) {
                return $collection->count() === 1 && $collection->first()->ip === '8.8.8.8';
            }))
            ->andReturn([
                [
                    'status' => 'success',
                    'countryCode' => 'US',
                    'lat' => 37.751,
                    'lon' => -97.822,
                    'query' => '8.8.8.8',
                ],
            ]);

        $job = new ProcessIpAddressesJob(collect([$localIp1, $localIp2, $localIp3, $localIp4, $localIp5, $publicIp]));
        $job->handle($mockResolver);

        // Should only create metadata for the public IP
        $this->assertDatabaseHas('ip_address_metadata', [
            'ip_address_id' => $publicIp->id,
            'country_code' => 'US',
        ]);

        // Should not create metadata for local IPs
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp1->id,
        ]);
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp2->id,
        ]);
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp3->id,
        ]);
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp4->id,
        ]);
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp5->id,
        ]);
    }

    public function test_skips_processing_when_all_ips_are_local()
    {
        $localIp1 = IpAddress::factory()->create(['ip' => '127.0.0.1']);
        $localIp2 = IpAddress::factory()->create(['ip' => '192.168.1.1']);
        $localIp3 = IpAddress::factory()->create(['ip' => '10.0.0.1']);

        $mockResolver = Mockery::mock(IpAddressMetadataResolverService::class);
        // Should never be called since all IPs are local
        $mockResolver->shouldNotReceive('resolve');

        $job = new ProcessIpAddressesJob(collect([$localIp1, $localIp2, $localIp3]));
        $job->handle($mockResolver);

        // Should not create any metadata
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp1->id,
        ]);
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp2->id,
        ]);
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $localIp3->id,
        ]);
    }

    public function test_handles_empty_ip_collection()
    {
        $mockResolver = Mockery::mock(IpAddressMetadataResolverService::class);
        $mockResolver->shouldReceive('resolve')
            ->andReturn([]);

        $job = new ProcessIpAddressesJob(collect([]));
        $job->handle($mockResolver);

        // Should complete without errors
        $this->assertTrue(true);
    }

    public function test_handles_mixed_success_and_failure_responses()
    {

        $ip1 = IpAddress::factory()->create(['ip' => '1.1.1.1']);
        $ip2 = IpAddress::factory()->create(['ip' => '2.2.2.2']);
        $ip3 = IpAddress::factory()->create(['ip' => '3.3.3.3']);

        $mockResolver = Mockery::mock(IpAddressMetadataResolverService::class);
        $mockResolver->shouldReceive('resolve')
            ->andReturn([
                [
                    'status' => 'success',
                    'countryCode' => 'US',
                    'lat' => 39.0458,
                    'lon' => -76.6413,
                    'query' => '1.1.1.1',
                ],
                [
                    'status' => 'fail',
                    'message' => 'Private IP address',
                    'query' => '2.2.2.2',
                ],
                [
                    'status' => 'success',
                    'countryCode' => 'GB',
                    'lat' => 51.5074,
                    'lon' => -0.1278,
                    'query' => '3.3.3.3',
                ],
            ]);

        $job = new ProcessIpAddressesJob(collect([$ip1, $ip2, $ip3]));
        $job->handle($mockResolver);

        // Should create metadata for successful IPs
        $this->assertDatabaseHas('ip_address_metadata', [
            'ip_address_id' => $ip1->id,
            'country_code' => 'US',
        ]);

        $this->assertDatabaseHas('ip_address_metadata', [
            'ip_address_id' => $ip3->id,
            'country_code' => 'GB',
        ]);

        // Should not create metadata for failed IP
        $this->assertDatabaseMissing('ip_address_metadata', [
            'ip_address_id' => $ip2->id,
        ]);
    }
}
