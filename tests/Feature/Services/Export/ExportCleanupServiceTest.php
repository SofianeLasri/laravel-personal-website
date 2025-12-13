<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Export;

use App\Services\Export\ExportCleanupService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ExportCleanupService::class)]
class ExportCleanupServiceTest extends TestCase
{
    private ExportCleanupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExportCleanupService::class);

        Storage::fake('local');
    }

    #[Test]
    public function it_returns_zero_when_temp_directory_does_not_exist(): void
    {
        $deleted = $this->service->cleanup();

        $this->assertEquals(0, $deleted);
    }

    #[Test]
    public function it_deletes_old_export_files(): void
    {
        // Create temp directory and old export files
        Storage::makeDirectory('temp');

        // Create an old file (10 days ago)
        Storage::put('temp/website-export-old.zip', 'old content');

        // Set the modification time to 10 days ago
        $filePath = Storage::path('temp/website-export-old.zip');
        touch($filePath, Carbon::now()->subDays(10)->timestamp);

        $deleted = $this->service->cleanup(7);

        $this->assertEquals(1, $deleted);
        $this->assertFalse(Storage::exists('temp/website-export-old.zip'));
    }

    #[Test]
    public function it_keeps_recent_export_files(): void
    {
        // Create temp directory
        Storage::makeDirectory('temp');

        // Create a recent file (2 days ago)
        Storage::put('temp/website-export-recent.zip', 'recent content');
        $filePath = Storage::path('temp/website-export-recent.zip');
        touch($filePath, Carbon::now()->subDays(2)->timestamp);

        $deleted = $this->service->cleanup(7);

        $this->assertEquals(0, $deleted);
        $this->assertTrue(Storage::exists('temp/website-export-recent.zip'));
    }

    #[Test]
    public function it_only_deletes_export_files(): void
    {
        // Create temp directory
        Storage::makeDirectory('temp');

        // Create an old non-export file
        Storage::put('temp/other-file.txt', 'other content');
        $filePath = Storage::path('temp/other-file.txt');
        touch($filePath, Carbon::now()->subDays(10)->timestamp);

        // Create an old export file
        Storage::put('temp/website-export-old.zip', 'export content');
        $exportPath = Storage::path('temp/website-export-old.zip');
        touch($exportPath, Carbon::now()->subDays(10)->timestamp);

        $deleted = $this->service->cleanup(7);

        $this->assertEquals(1, $deleted);
        $this->assertTrue(Storage::exists('temp/other-file.txt'));
        $this->assertFalse(Storage::exists('temp/website-export-old.zip'));
    }

    #[Test]
    public function it_uses_custom_keep_days(): void
    {
        // Create temp directory
        Storage::makeDirectory('temp');

        // Create a file 3 days old
        Storage::put('temp/website-export-3days.zip', 'content');
        $filePath = Storage::path('temp/website-export-3days.zip');
        touch($filePath, Carbon::now()->subDays(3)->timestamp);

        // Should not be deleted with 7 days threshold
        $this->assertEquals(0, $this->service->cleanup(7));
        $this->assertTrue(Storage::exists('temp/website-export-3days.zip'));

        // Should be deleted with 2 days threshold
        $this->assertEquals(1, $this->service->cleanup(2));
        $this->assertFalse(Storage::exists('temp/website-export-3days.zip'));
    }

    #[Test]
    public function it_lists_export_files(): void
    {
        // Create temp directory
        Storage::makeDirectory('temp');

        // Create export files
        Storage::put('temp/website-export-1.zip', 'content 1');
        Storage::put('temp/website-export-2.zip', 'content 2');
        Storage::put('temp/other-file.txt', 'other');

        $exports = $this->service->listExports();

        $this->assertCount(2, $exports);

        // Verify structure
        foreach ($exports as $export) {
            $this->assertArrayHasKey('path', $export);
            $this->assertArrayHasKey('size', $export);
            $this->assertArrayHasKey('modified', $export);
            $this->assertStringContainsString('website-export-', $export['path']);
        }
    }

    #[Test]
    public function it_returns_empty_array_when_no_exports(): void
    {
        // Create temp directory with no export files
        Storage::makeDirectory('temp');
        Storage::put('temp/other-file.txt', 'content');

        $exports = $this->service->listExports();

        $this->assertEmpty($exports);
    }

    #[Test]
    public function it_returns_empty_array_when_temp_does_not_exist(): void
    {
        $exports = $this->service->listExports();

        $this->assertEmpty($exports);
    }

    #[Test]
    public function it_sorts_exports_by_modification_date(): void
    {
        // Create temp directory
        Storage::makeDirectory('temp');

        // Create files with different modification times
        Storage::put('temp/website-export-old.zip', 'old');
        Storage::put('temp/website-export-middle.zip', 'middle');
        Storage::put('temp/website-export-new.zip', 'new');

        touch(Storage::path('temp/website-export-old.zip'), Carbon::now()->subDays(10)->timestamp);
        touch(Storage::path('temp/website-export-middle.zip'), Carbon::now()->subDays(5)->timestamp);
        touch(Storage::path('temp/website-export-new.zip'), Carbon::now()->subDays(1)->timestamp);

        $exports = $this->service->listExports();

        $this->assertCount(3, $exports);
        // Newest first
        $this->assertStringContainsString('new', $exports[0]['path']);
        $this->assertStringContainsString('middle', $exports[1]['path']);
        $this->assertStringContainsString('old', $exports[2]['path']);
    }

    #[Test]
    public function it_includes_file_size_in_list(): void
    {
        // Create temp directory
        Storage::makeDirectory('temp');

        $content = str_repeat('x', 1024); // 1KB
        Storage::put('temp/website-export-sized.zip', $content);

        $exports = $this->service->listExports();

        $this->assertCount(1, $exports);
        $this->assertEquals(1024, $exports[0]['size']);
    }

    #[Test]
    public function it_deletes_multiple_old_files(): void
    {
        // Create temp directory
        Storage::makeDirectory('temp');

        // Create multiple old files
        for ($i = 1; $i <= 5; $i++) {
            Storage::put("temp/website-export-old-{$i}.zip", "content {$i}");
            touch(Storage::path("temp/website-export-old-{$i}.zip"), Carbon::now()->subDays(10)->timestamp);
        }

        // Create a recent file
        Storage::put('temp/website-export-recent.zip', 'recent');
        touch(Storage::path('temp/website-export-recent.zip'), Carbon::now()->subDays(1)->timestamp);

        $deleted = $this->service->cleanup(7);

        $this->assertEquals(5, $deleted);
        $this->assertTrue(Storage::exists('temp/website-export-recent.zip'));
    }
}
