<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Export;

use App\Services\Export\FileExportService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

#[CoversClass(FileExportService::class)]
class FileExportServiceTest extends TestCase
{
    private FileExportService $service;

    private string $tempZipPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FileExportService::class);
        $this->tempZipPath = sys_get_temp_dir().'/test-file-export-'.uniqid().'.zip';

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
    public function it_exports_files_to_zip(): void
    {
        // Create test files
        Storage::disk('public')->put('test-file.txt', 'Hello World');
        Storage::disk('public')->put('uploads/image.jpg', 'fake image content');

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $textContent = $zip->getFromName('files/test-file.txt');
        $this->assertEquals('Hello World', $textContent);

        $imageContent = $zip->getFromName('files/uploads/image.jpg');
        $this->assertEquals('fake image content', $imageContent);

        $zip->close();
    }

    #[Test]
    public function it_exports_files_in_nested_directories(): void
    {
        // Create nested directory structure
        Storage::disk('public')->put('level1/file1.txt', 'content1');
        Storage::disk('public')->put('level1/level2/file2.txt', 'content2');
        Storage::disk('public')->put('level1/level2/level3/file3.txt', 'content3');

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $this->assertEquals('content1', $zip->getFromName('files/level1/file1.txt'));
        $this->assertEquals('content2', $zip->getFromName('files/level1/level2/file2.txt'));
        $this->assertEquals('content3', $zip->getFromName('files/level1/level2/level3/file3.txt'));

        $zip->close();
    }

    #[Test]
    public function it_handles_empty_storage(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        // Add a placeholder to ensure ZIP is created
        $zip->addFromString('.keep', '');

        // Should not throw even with empty storage
        $this->service->export($zip);

        $zip->close();

        $this->assertFileExists($this->tempZipPath);

        // Verify no files were added to the 'files/' directory
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);
        $fileFound = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'files/')) {
                $fileFound = true;
                break;
            }
        }
        $this->assertFalse($fileFound, 'No files should be in files/ directory');
        $zip->close();
    }

    #[Test]
    public function it_gets_all_files_from_disk(): void
    {
        // Create test files
        Storage::disk('public')->put('file1.txt', 'content1');
        Storage::disk('public')->put('dir/file2.txt', 'content2');
        Storage::disk('public')->put('dir/subdir/file3.txt', 'content3');

        $files = $this->service->getAllFiles(Storage::disk('public'));

        $this->assertCount(3, $files);
        $this->assertContains('file1.txt', $files);
        $this->assertContains('dir/file2.txt', $files);
        $this->assertContains('dir/subdir/file3.txt', $files);
    }

    #[Test]
    public function it_counts_files_correctly(): void
    {
        // Create test files
        Storage::disk('public')->put('file1.txt', 'content1');
        Storage::disk('public')->put('file2.txt', 'content2');
        Storage::disk('public')->put('uploads/file3.txt', 'content3');

        $count = $this->service->countFiles();

        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_returns_zero_for_empty_storage(): void
    {
        $count = $this->service->countFiles();

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_exports_binary_files(): void
    {
        // Create a binary file (simulated image)
        $binaryContent = chr(0xFF).chr(0xD8).chr(0xFF).chr(0xE0).'binary data';
        Storage::disk('public')->put('binary-file.bin', $binaryContent);

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $extractedContent = $zip->getFromName('files/binary-file.bin');
        $this->assertEquals($binaryContent, $extractedContent);

        $zip->close();
    }

    #[Test]
    public function it_handles_files_with_special_characters(): void
    {
        Storage::disk('public')->put('file with spaces.txt', 'content');
        Storage::disk('public')->put('file-with-dashes.txt', 'content');
        Storage::disk('public')->put('file_with_underscores.txt', 'content');

        $zip = new ZipArchive;
        $zip->open($this->tempZipPath, ZipArchive::CREATE);

        $this->service->export($zip);

        $zip->close();

        // Re-open to verify contents
        $zip = new ZipArchive;
        $zip->open($this->tempZipPath);

        $this->assertNotFalse($zip->getFromName('files/file with spaces.txt'));
        $this->assertNotFalse($zip->getFromName('files/file-with-dashes.txt'));
        $this->assertNotFalse($zip->getFromName('files/file_with_underscores.txt'));

        $zip->close();
    }
}
