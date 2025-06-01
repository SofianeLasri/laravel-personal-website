<?php

namespace Services;

use App\Services\IpAddressMetadataResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use Tests\TestCase;

#[CoversClass(IpAddressMetadataResolverService::class)]
class IpAddressMetadataResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_ip_addresses_successfully()
    {
        Exceptions::fake();

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

        $service = new IpAddressMetadataResolverService;

        $ipAddresses = IpAddress::factory()->count(2)->create();
        $result = $service->resolve($ipAddresses);

        Exceptions::assertNothingReported();
        $this->assertCount(2, $result);
        $this->assertEquals('US', $result[0]['countryCode']);
        $this->assertEquals('CA', $result[1]['countryCode']);
    }
}
