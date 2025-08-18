<?php

namespace Tests\Unit\Models;

use App\Models\IpAddressMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use Tests\TestCase;

#[CoversClass(IpAddressMetadata::class)]
class IpAddressMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes()
    {
        $expectedFillable = [
            'ip_address_id',
            'country_code',
            'lat',
            'lon',
            'avg_request_interval',
            'total_requests',
            'first_seen_at',
            'last_seen_at',
            'last_bot_analysis_at',
        ];

        $model = new IpAddressMetadata;

        $this->assertEquals($expectedFillable, $model->getFillable());
    }

    public function test_timestamps_are_disabled()
    {
        $model = new IpAddressMetadata;

        $this->assertFalse($model->timestamps);
    }

    public function test_belongs_to_ip_address_relationship()
    {
        $ipAddress = IpAddress::factory()->create();
        $metadata = IpAddressMetadata::factory()->create([
            'ip_address_id' => $ipAddress->id,
        ]);

        $this->assertInstanceOf(IpAddress::class, $metadata->ipAddress);
        $this->assertEquals($ipAddress->id, $metadata->ipAddress->id);
    }

    public function test_can_create_ip_address_metadata()
    {
        $ipAddress = IpAddress::factory()->create();

        $metadata = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'lat' => 37.7749,
            'lon' => -122.4194,
        ]);

        $this->assertDatabaseHas('ip_address_metadata', [
            'id' => $metadata->id,
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'lat' => 37.7749,
            'lon' => -122.4194,
        ]);
    }

    public function test_country_codes_constant_is_available()
    {
        $this->assertIsArray(IpAddressMetadata::COUNTRY_CODES);
        $this->assertNotEmpty(IpAddressMetadata::COUNTRY_CODES);
        $this->assertContains('US', IpAddressMetadata::COUNTRY_CODES);
        $this->assertContains('CA', IpAddressMetadata::COUNTRY_CODES);
        $this->assertContains('FR', IpAddressMetadata::COUNTRY_CODES);
    }

    public function test_can_create_metadata_with_different_country_codes()
    {
        $ipAddress1 = IpAddress::factory()->create();
        $ipAddress2 = IpAddress::factory()->create();

        $metadata1 = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress1->id,
            'country_code' => 'US',
            'lat' => 37.7749,
            'lon' => -122.4194,
        ]);

        $metadata2 = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress2->id,
            'country_code' => 'CA',
            'lat' => 45.5017,
            'lon' => -73.5673,
        ]);

        $this->assertEquals('US', $metadata1->country_code);
        $this->assertEquals('CA', $metadata2->country_code);
    }

    public function test_can_create_metadata_with_null_coordinates()
    {
        $ipAddress = IpAddress::factory()->create();

        $metadata = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'lat' => null,
            'lon' => null,
        ]);

        $this->assertDatabaseHas('ip_address_metadata', [
            'id' => $metadata->id,
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'lat' => null,
            'lon' => null,
        ]);
    }

    public function test_ip_address_id_is_required()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        IpAddressMetadata::create([
            'country_code' => 'US',
            'lat' => 37.7749,
            'lon' => -122.4194,
        ]);
    }

    public function test_coordinates_can_be_floats()
    {
        $ipAddress = IpAddress::factory()->create();

        $metadata = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'lat' => 37.774929,
            'lon' => -122.419416,
        ]);

        $this->assertIsFloat($metadata->lat);
        $this->assertIsFloat($metadata->lon);
        $this->assertEquals(37.774929, $metadata->lat);
        $this->assertEquals(-122.419416, $metadata->lon);
    }

    public function test_country_code_must_be_valid_enum_value()
    {
        $ipAddress = IpAddress::factory()->create();

        $metadata = IpAddressMetadata::create([
            'ip_address_id' => $ipAddress->id,
            'country_code' => 'US',
            'lat' => 37.7749,
            'lon' => -122.4194,
        ]);

        $this->assertEquals('US', $metadata->country_code);
        $this->assertContains('US', IpAddressMetadata::COUNTRY_CODES);
    }
}
