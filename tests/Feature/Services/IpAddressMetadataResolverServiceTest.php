<?php

namespace Tests\Feature\Services;

use App\Services\IpAddressMetadataResolverService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use Tests\TestCase;

#[CoversClass(IpAddressMetadataResolverService::class)]
class IpAddressMetadataResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.ip-address-resolver', [
            'url' => 'https://api.example.com/batch',
            'max_ip_addresses_per_call' => 100,
            'call_limit_per_minute' => 15,
        ]);

        Cache::flush();
    }

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

        $ip1 = IpAddress::factory()->create(['ip' => '208.80.152.201']);
        $ip2 = IpAddress::factory()->create(['ip' => '24.48.0.1']);
        $ipAddresses = collect([$ip1, $ip2]);

        $result = IpAddressMetadataResolverService::resolve($ipAddresses);

        Exceptions::assertNothingReported();
        $this->assertCount(2, $result);
        $this->assertEquals('success', $result[0]['status']);
        $this->assertEquals('US', $result[0]['countryCode']);
        $this->assertEquals('CA', $result[1]['countryCode']);
    }

    public function test_respects_call_limit_per_minute()
    {
        Cache::put('services.ip-address-resolver.calls_count', 15, 60);

        $ipAddresses = IpAddress::factory()->count(2)->create();

        $result = IpAddressMetadataResolverService::resolve($ipAddresses);

        $this->assertCount(2, $result);
        $this->assertEquals('fail', $result[0]['status']);
        $this->assertEquals('Max calls per minute reached', $result[0]['message']);
    }

    public function test_limits_ip_addresses_per_call()
    {
        Config::set('services.ip-address-resolver.max_ip_addresses_per_call', 2);

        Http::fake([
            '*' => Http::response([
                ['status' => 'success', 'countryCode' => 'US', 'query' => 'ip1'],
                ['status' => 'success', 'countryCode' => 'CA', 'query' => 'ip2'],
            ]),
        ]);

        $ipAddresses = IpAddress::factory()->count(5)->create();

        $result = IpAddressMetadataResolverService::resolve($ipAddresses);

        // Should only process first 2 IP addresses
        $this->assertCount(2, $result);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return count($body) === 2;
        });
    }

    public function test_handles_422_unprocessable_entity_error()
    {
        Http::fake([
            '*' => Http::response(['message' => 'Invalid input'], 422),
        ]);

        $ipAddresses = IpAddress::factory()->count(2)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The API rejected the request with a 422 unprocessable entity status code. Invalid input');

        IpAddressMetadataResolverService::resolve($ipAddresses);
    }

    public function test_handles_server_error_gracefully()
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $ipAddresses = IpAddress::factory()->count(2)->create();

        $result = IpAddressMetadataResolverService::resolve($ipAddresses);

        $this->assertCount(2, $result);
        $this->assertEquals('fail', $result[0]['status']);
        $this->assertEquals('Server error encountered', $result[0]['message']);
    }

    public function test_handles_unexpected_status_code()
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        $ipAddresses = IpAddress::factory()->count(2)->create();

        $result = IpAddressMetadataResolverService::resolve($ipAddresses);

        $this->assertCount(2, $result);
        $this->assertEquals('fail', $result[0]['status']);
        $this->assertEquals('Unexpected status code returned', $result[0]['message']);
    }

    public function test_handles_connection_exception()
    {
        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        $ipAddresses = IpAddress::factory()->count(2)->create();

        $this->expectException(ConnectionException::class);

        IpAddressMetadataResolverService::resolve($ipAddresses);
    }

    public function test_increments_call_count()
    {
        Http::fake([
            '*' => Http::response([]),
        ]);

        $this->assertEquals(0, Cache::get('services.ip-address-resolver.calls_count', 0));

        $ipAddresses = IpAddress::factory()->count(2)->create();
        IpAddressMetadataResolverService::resolve($ipAddresses);

        $this->assertEquals(1, Cache::get('services.ip-address-resolver.calls_count', 0));
    }

    public function test_sends_correct_url_with_fields_parameter()
    {
        Http::fake([
            '*' => Http::response([]),
        ]);

        $ipAddresses = IpAddress::factory()->count(1)->create();
        IpAddressMetadataResolverService::resolve($ipAddresses);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fields=status,message,countryCode,lat,lon,query');
        });
    }

    public function test_sends_ip_addresses_as_array()
    {
        Http::fake([
            '*' => Http::response([]),
        ]);

        $ip1 = IpAddress::factory()->create(['ip' => '192.168.1.1']);
        $ip2 = IpAddress::factory()->create(['ip' => '10.0.0.1']);

        IpAddressMetadataResolverService::resolve(collect([$ip1, $ip2]));

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body === ['192.168.1.1', '10.0.0.1'];
        });
    }
}
