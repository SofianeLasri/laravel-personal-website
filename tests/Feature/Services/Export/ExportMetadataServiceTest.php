<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Export;

use App\Services\Export\ExportMetadataService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(ExportMetadataService::class)]
class ExportMetadataServiceTest extends TestCase
{
    private ExportMetadataService $service;

    private string $tempZipPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExportMetadataService::class);
        $this->tempZipPath = sys_get_temp_dir().'/test-metadata-'.uniqid().'.zip';

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempZipPath)) {
            unlink($this->tempZipPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_creates_metadata_in_zip(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->create($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadataJson = $zip->getFromName('export-metadata.json');
        $this->assertNotFalse($metadataJson);

        $metadata = json_decode($metadataJson, true);
        $this->assertIsArray($metadata);

        $zip->close();
    }

    #[Test]
    public function it_includes_export_date(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->create($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertArrayHasKey('export_date', $metadata);
        $this->assertNotEmpty($metadata['export_date']);

        $zip->close();
    }

    #[Test]
    public function it_includes_laravel_version(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->create($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertArrayHasKey('laravel_version', $metadata);
        $this->assertEquals(app()->version(), $metadata['laravel_version']);

        $zip->close();
    }

    #[Test]
    public function it_includes_tables_list(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->create($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertArrayHasKey('tables_exported', $metadata);
        $this->assertIsArray($metadata['tables_exported']);
        $this->assertNotEmpty($metadata['tables_exported']);

        $zip->close();
    }

    #[Test]
    public function it_includes_files_count(): void
    {
        // Create some test files
        Storage::disk('public')->put('file1.txt', 'content');
        Storage::disk('public')->put('file2.txt', 'content');

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->create($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertArrayHasKey('files_count', $metadata);
        $this->assertEquals(2, $metadata['files_count']);

        $zip->close();
    }

    #[Test]
    public function it_reads_metadata_from_zip(): void
    {
        // Create a ZIP with metadata
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $testMetadata = [
            'export_date' => '2024-01-01T00:00:00Z',
            'laravel_version' => '11.0.0',
            'tables_exported' => ['users', 'posts'],
            'files_count' => 10,
        ];
        $zip->addFromString('export-metadata.json', json_encode($testMetadata));
        $zip->close();

        // Read metadata back
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadata = $this->service->read($zip);

        $this->assertNotNull($metadata);
        $this->assertEquals('2024-01-01T00:00:00Z', $metadata['export_date']);
        $this->assertEquals('11.0.0', $metadata['laravel_version']);
        $this->assertEquals(['users', 'posts'], $metadata['tables_exported']);
        $this->assertEquals(10, $metadata['files_count']);

        $zip->close();
    }

    #[Test]
    public function it_returns_null_when_metadata_not_found(): void
    {
        // Create an empty ZIP
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('dummy.txt', 'dummy content');
        $zip->close();

        // Try to read metadata
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadata = $this->service->read($zip);

        $this->assertNull($metadata);

        $zip->close();
    }

    #[Test]
    public function it_includes_database_name(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->create($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $metadata = json_decode($zip->getFromName('export-metadata.json'), true);
        $this->assertArrayHasKey('database_name', $metadata);

        $zip->close();
    }

    #[Test]
    public function it_round_trips_metadata(): void
    {
        // Create metadata
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $this->service->create($zip);
        $zip->close();

        // Read it back
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);
        $metadata = $this->service->read($zip);
        $zip->close();

        $this->assertNotNull($metadata);
        $this->assertArrayHasKey('export_date', $metadata);
        $this->assertArrayHasKey('laravel_version', $metadata);
        $this->assertArrayHasKey('database_name', $metadata);
        $this->assertArrayHasKey('tables_exported', $metadata);
        $this->assertArrayHasKey('files_count', $metadata);
    }
}
