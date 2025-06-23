<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\Creation;
use App\Models\Technology;
use App\Models\User;
use App\Services\WebsiteImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;
use ZipArchive;

class DataManagementControllerImportTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_upload_import_file_validates_file_type(): void
    {
        $invalidFile = UploadedFile::fake()->create('test.txt', 100);

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $invalidFile,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    public function test_upload_import_file_validates_file_size(): void
    {
        $largeFile = UploadedFile::fake()->create('test.zip', 200000); // 200MB

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $largeFile,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    public function test_upload_import_file_validates_zip_structure(): void
    {
        // Create invalid ZIP file
        $invalidZip = $this->createInvalidZipFile();

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $invalidZip,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    public function test_upload_valid_import_file_succeeds(): void
    {
        $validZip = $this->createValidZipFile();

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'message' => 'File uploaded and validated successfully',
        ]);
        $response->assertJsonStructure([
            'file_path',
            'metadata' => [
                'export_date',
                'laravel_version',
                'database_name',
                'files_count',
            ],
        ]);
    }

    public function test_import_requires_file_path(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'confirm_import' => true,
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['file_path']);
    }

    public function test_import_requires_confirmation(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => 'temp/test-import.zip',
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['confirm_import']);
    }

    public function test_import_fails_if_file_not_found(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => 'temp/nonexistent.zip',
                'confirm_import' => true,
            ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson([
            'message' => 'Import file not found',
        ]);
    }

    public function test_successful_import_returns_statistics(): void
    {
        // Create test data first
        Technology::factory()->create(['name' => 'Original Tech']);

        // Create and upload valid import file
        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'message' => 'Import completed successfully',
        ]);
        $response->assertJsonStructure([
            'stats' => [
                'tables_imported',
                'records_imported',
                'files_imported',
                'import_date',
            ],
        ]);

        // Verify data was imported
        $this->assertDatabaseHas('technologies', ['name' => 'Test Technology']);

        // Verify original data was replaced
        $this->assertDatabaseMissing('technologies', ['name' => 'Original Tech']);
    }

    public function test_import_replaces_existing_data(): void
    {
        // Create existing data
        $existingTech = Technology::factory()->create(['name' => 'Existing Technology']);
        $existingCreation = Creation::factory()->create(['name' => 'Existing Creation']);

        // Create import file with different data
        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();

        // Verify existing data was replaced
        $this->assertDatabaseMissing('technologies', ['name' => 'Existing Technology']);
        $this->assertDatabaseMissing('creations', ['name' => 'Existing Creation']);

        // Verify new data was imported
        $this->assertDatabaseHas('technologies', ['name' => 'Test Technology']);
    }

    public function test_import_restores_files(): void
    {
        // Create existing file
        Storage::disk('public')->put('uploads/existing-file.txt', 'existing content');

        // Create import file with different files
        $validZip = $this->createValidZipFileWithFiles();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();

        // Verify existing file was replaced
        $this->assertFalse(Storage::disk('public')->exists('uploads/existing-file.txt'));

        // Verify new files were imported
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image.jpg'));
        $this->assertEquals('test image content', Storage::disk('public')->get('uploads/test-image.jpg'));
    }

    public function test_get_import_metadata_returns_metadata(): void
    {
        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        $response = $this
            ->post('/dashboard/data-management/metadata', [
                'file_path' => $filePath,
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'export_date',
            'laravel_version',
            'database_name',
            'files_count',
        ]);
    }

    public function test_cancel_import_deletes_uploaded_file(): void
    {
        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        // Verify file exists
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        $response = $this
            ->delete('/dashboard/data-management/cancel', [
                'file_path' => $filePath,
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'message' => 'Import cancelled successfully',
        ]);

        // Verify file was deleted
        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }

    public function test_import_handles_service_failure(): void
    {
        $this->mock(WebsiteImportService::class, function ($mock) {
            $mock->shouldReceive('validateImportFile')
                ->once()
                ->andReturn(['valid' => true, 'errors' => [], 'metadata' => []]);

            $mock->shouldReceive('importWebsite')
                ->once()
                ->andThrow(new \RuntimeException('Import failed'));
        });

        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response->assertJson([
            'message' => 'Import failed: Import failed',
        ]);
    }

    public function test_all_endpoints_require_authentication(): void
    {
        // Logout the user that was logged in during setUp
        auth()->logout();

        $endpoints = [
            ['POST', '/dashboard/data-management/upload'],
            ['POST', '/dashboard/data-management/import'],
            ['POST', '/dashboard/data-management/metadata'],
            ['DELETE', '/dashboard/data-management/cancel'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->call($method, $endpoint);
            $response->assertRedirect('/login');
        }
    }

    private function createValidZipFile(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Add metadata
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 1,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add database files
        $technologiesData = [
            [
                'id' => 1,
                'name' => 'Test Technology',
                'type' => 'framework',
                'svg_icon' => '<svg>test</svg>',
                'description_translation_key_id' => 1,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/technologies.json', json_encode($technologiesData));

        // Add required translation_keys data
        $translationKeysData = [
            [
                'id' => 1,
                'key' => 'technology.test-description',
            ],
        ];
        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));

        // Add required translations data
        $translationsData = [
            [
                'id' => 1,
                'translation_key_id' => 1,
                'locale' => 'en',
                'text' => 'Test technology description',
            ],
        ];
        $zip->addFromString('database/translations.json', json_encode($translationsData));

        $zip->addFromString('database/users.json', json_encode([]));

        $zip->close();

        return new UploadedFile($zipPath, 'test-export.zip', 'application/zip', null, true);
    }

    private function createValidZipFileWithFiles(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Add metadata
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 1,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add database files
        $zip->addFromString('database/technologies.json', json_encode([]));
        $zip->addFromString('database/users.json', json_encode([]));

        // Add files
        $zip->addFromString('files/uploads/test-image.jpg', 'test image content');

        $zip->close();

        return new UploadedFile($zipPath, 'test-export.zip', 'application/zip', null, true);
    }

    private function createInvalidZipFile(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'invalid_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Create ZIP without required structure
        $zip->addFromString('random-file.txt', 'random content');
        $zip->close();

        return new UploadedFile($zipPath, 'invalid-export.zip', 'application/zip', null, true);
    }
}
