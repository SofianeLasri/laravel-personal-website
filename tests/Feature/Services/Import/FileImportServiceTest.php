<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Import;

use App\Services\Import\FileImportService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(FileImportService::class)]
class FileImportServiceTest extends TestCase
{
    private FileImportService $service;

    private string $tempZipPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FileImportService::class);
        $this->tempZipPath = sys_get_temp_dir().'/test-file-import-'.uniqid().'.zip';

        Storage::fake('public');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempZipPath)) {
            unlink($this->tempZipPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_imports_files_from_zip(): void
    {
        // Create a ZIP with files
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('files/test-file.txt', 'Hello World');
        $zip->addFromString('files/uploads/image.jpg', 'fake image content');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $filesImported = $this->service->import($zip);

        $zip->close();

        $this->assertEquals(2, $filesImported);
        $this->assertTrue(Storage::disk('public')->exists('test-file.txt'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/image.jpg'));
        $this->assertEquals('Hello World', Storage::disk('public')->get('test-file.txt'));
        $this->assertEquals('fake image content', Storage::disk('public')->get('uploads/image.jpg'));
    }

    #[Test]
    public function it_creates_directories_for_nested_files(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('files/level1/level2/level3/deep-file.txt', 'deep content');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $filesImported = $this->service->import($zip);

        $zip->close();

        $this->assertEquals(1, $filesImported);
        $this->assertTrue(Storage::disk('public')->exists('level1/level2/level3/deep-file.txt'));
        $this->assertEquals('deep content', Storage::disk('public')->get('level1/level2/level3/deep-file.txt'));
    }

    #[Test]
    public function it_clears_existing_files_before_import(): void
    {
        // Create existing files
        Storage::disk('public')->put('existing-file.txt', 'old content');
        Storage::disk('public')->put('uploads/old-image.jpg', 'old image');

        // Create a ZIP with new files
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('files/new-file.txt', 'new content');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $this->service->import($zip);

        $zip->close();

        // Old files should be deleted
        $this->assertFalse(Storage::disk('public')->exists('existing-file.txt'));
        $this->assertFalse(Storage::disk('public')->exists('uploads/old-image.jpg'));
        // New file should exist
        $this->assertTrue(Storage::disk('public')->exists('new-file.txt'));
    }

    #[Test]
    public function it_ignores_non_file_entries_in_zip(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        // Add a database file (not in files/ prefix)
        $zip->addFromString('database/users.json', '[]');
        // Add a metadata file
        $zip->addFromString('export-metadata.json', '{}');
        // Add actual files
        $zip->addFromString('files/real-file.txt', 'content');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $filesImported = $this->service->import($zip);

        $zip->close();

        $this->assertEquals(1, $filesImported);
        $this->assertTrue(Storage::disk('public')->exists('real-file.txt'));
    }

    #[Test]
    public function it_skips_directory_entries(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        // Add a directory entry (ends with /)
        $zip->addEmptyDir('files/empty-dir');
        // Add a file
        $zip->addFromString('files/file.txt', 'content');
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $filesImported = $this->service->import($zip);

        $zip->close();

        // Should only count files, not directories
        $this->assertEquals(1, $filesImported);
    }

    #[Test]
    public function it_handles_empty_zip(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('.keep', ''); // Add something so ZIP is created
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $filesImported = $this->service->import($zip);

        $zip->close();

        $this->assertEquals(0, $filesImported);
    }

    #[Test]
    public function it_clears_local_temp_files(): void
    {
        // Create temp files on local disk
        Storage::disk('local')->put('temp/website-export-old.zip', 'old export');
        Storage::disk('local')->put('temp/other-temp-file.txt', 'temp content');

        $this->service->clearLocal(Storage::disk('local'));

        $this->assertFalse(Storage::disk('local')->exists('temp/website-export-old.zip'));
        $this->assertFalse(Storage::disk('local')->exists('temp/other-temp-file.txt'));
    }

    #[Test]
    public function it_preserves_gitignore_on_local_disk(): void
    {
        Storage::disk('local')->put('.gitignore', '*');
        Storage::disk('local')->put('some-file.txt', 'content');

        $this->service->clearLocal(Storage::disk('local'));

        $this->assertTrue(Storage::disk('local')->exists('.gitignore'));
        $this->assertFalse(Storage::disk('local')->exists('some-file.txt'));
    }

    #[Test]
    public function it_preserves_framework_directory(): void
    {
        Storage::disk('local')->put('framework/sessions/abc123', 'session data');
        Storage::disk('local')->put('framework/cache/xyz', 'cache data');
        Storage::disk('local')->put('regular-file.txt', 'content');

        $this->service->clearLocal(Storage::disk('local'));

        $this->assertTrue(Storage::disk('local')->exists('framework/sessions/abc123'));
        $this->assertTrue(Storage::disk('local')->exists('framework/cache/xyz'));
        $this->assertFalse(Storage::disk('local')->exists('regular-file.txt'));
    }

    #[Test]
    public function it_imports_binary_files(): void
    {
        // Create a binary content
        $binaryContent = chr(0xFF).chr(0xD8).chr(0xFF).chr(0xE0).'binary data';

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);
        $zip->addFromString('files/binary-file.bin', $binaryContent);
        $zip->close();

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $filesImported = $this->service->import($zip);

        $zip->close();

        $this->assertEquals(1, $filesImported);
        $this->assertEquals($binaryContent, Storage::disk('public')->get('binary-file.bin'));
    }
}
