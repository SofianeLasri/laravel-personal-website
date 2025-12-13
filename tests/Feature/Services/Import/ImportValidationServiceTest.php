<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Import;

use App\Services\Import\ImportValidationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(ImportValidationService::class)]
class ImportValidationServiceTest extends TestCase
{
    private ImportValidationService $service;

    private string $tempZipPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ImportValidationService::class);
        $this->tempZipPath = sys_get_temp_dir().'/test-validation-'.uniqid().'.zip';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempZipPath)) {
            unlink($this->tempZipPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_validates_valid_structure(): void
    {
        // Create a valid export ZIP
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('export-metadata.json', json_encode(['export_date' => '2024-01-01']));
        $zip->addFromString('database/translation_keys.json', '[]');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        // Should not throw
        $this->service->validateStructure($zip);

        $zip->close();

        $this->assertTrue(true); // If we get here, validation passed
    }

    #[Test]
    public function it_throws_exception_for_missing_metadata(): void
    {
        // Create a ZIP without metadata
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('database/translation_keys.json', '[]');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid export file: missing metadata');

        $this->service->validateStructure($zip);

        $zip->close();
    }

    #[Test]
    public function it_throws_exception_for_missing_database_files(): void
    {
        // Create a ZIP without database files
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('export-metadata.json', json_encode(['export_date' => '2024-01-01']));
        $zip->addFromString('files/test.txt', 'content');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid export file: missing database files');

        $this->service->validateStructure($zip);

        $zip->close();
    }

    #[Test]
    public function it_gets_metadata_from_file(): void
    {
        $testMetadata = [
            'export_date' => '2024-01-01T00:00:00Z',
            'laravel_version' => '11.0.0',
            'tables_exported' => ['users', 'posts'],
            'files_count' => 10,
        ];

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('export-metadata.json', json_encode($testMetadata));
        $zip->close();

        $metadata = $this->service->getMetadata($this->tempZipPath);

        $this->assertNotNull($metadata);
        $this->assertEquals('2024-01-01T00:00:00Z', $metadata['export_date']);
        $this->assertEquals('11.0.0', $metadata['laravel_version']);
        $this->assertEquals(['users', 'posts'], $metadata['tables_exported']);
        $this->assertEquals(10, $metadata['files_count']);
    }

    #[Test]
    public function it_returns_null_for_non_existent_file(): void
    {
        $metadata = $this->service->getMetadata('/non/existent/path.zip');

        $this->assertNull($metadata);
    }

    #[Test]
    public function it_returns_null_for_invalid_zip(): void
    {
        // Create a non-zip file
        file_put_contents($this->tempZipPath, 'not a zip file');

        $metadata = $this->service->getMetadata($this->tempZipPath);

        $this->assertNull($metadata);
    }

    #[Test]
    public function it_returns_null_when_metadata_missing_in_zip(): void
    {
        // Create a ZIP without metadata
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('other-file.txt', 'content');
        $zip->close();

        $metadata = $this->service->getMetadata($this->tempZipPath);

        $this->assertNull($metadata);
    }

    #[Test]
    public function it_validates_file_successfully(): void
    {
        // Create a valid export ZIP
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('export-metadata.json', json_encode(['export_date' => '2024-01-01']));
        $zip->addFromString('database/translation_keys.json', '[]');
        $zip->close();

        $result = $this->service->validateFile($this->tempZipPath);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotNull($result['metadata']);
    }

    #[Test]
    public function it_returns_error_for_non_existent_file_validation(): void
    {
        $result = $this->service->validateFile('/non/existent/path.zip');

        $this->assertFalse($result['valid']);
        $this->assertContains('File does not exist', $result['errors']);
        $this->assertNull($result['metadata']);
    }

    #[Test]
    public function it_returns_error_for_invalid_zip_validation(): void
    {
        // Create a non-zip file
        file_put_contents($this->tempZipPath, 'not a zip file');

        $result = $this->service->validateFile($this->tempZipPath);

        $this->assertFalse($result['valid']);
        $this->assertContains('Cannot open ZIP file', $result['errors']);
    }

    #[Test]
    public function it_returns_error_for_invalid_structure(): void
    {
        // Create a ZIP without required structure
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('random-file.txt', 'content');
        $zip->close();

        $result = $this->service->validateFile($this->tempZipPath);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Test]
    public function it_includes_metadata_in_validation_result(): void
    {
        $testMetadata = [
            'export_date' => '2024-06-15T12:00:00Z',
            'laravel_version' => '11.0.0',
        ];

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('export-metadata.json', json_encode($testMetadata));
        $zip->addFromString('database/users.json', '[]');
        $zip->close();

        $result = $this->service->validateFile($this->tempZipPath);

        $this->assertTrue($result['valid']);
        $this->assertEquals('2024-06-15T12:00:00Z', $result['metadata']['export_date']);
        $this->assertEquals('11.0.0', $result['metadata']['laravel_version']);
    }
}
