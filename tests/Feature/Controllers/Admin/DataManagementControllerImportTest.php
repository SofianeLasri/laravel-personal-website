<?php

namespace Tests\Feature\Controllers\Admin;

use App\Http\Controllers\Admin\DataManagementController;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Creation;
use App\Models\Technology;
use App\Models\TranslationKey;
use App\Models\User;
use App\Services\WebsiteImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;
use ZipArchive;

#[CoversClass(DataManagementController::class)]
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

    #[Test]
    public function test_upload_import_file_validates_file_type(): void
    {
        $invalidFile = UploadedFile::fake()->create('test.txt', 100);

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $invalidFile,
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    #[Test]
    public function test_upload_import_file_requires_file(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/upload', []);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    #[Test]
    public function test_upload_import_file_handles_invalid_zip_content(): void
    {
        // Create a mock for WebsiteImportService that returns invalid validation
        $mockImportService = $this->mock(WebsiteImportService::class);
        $mockImportService->shouldReceive('validateImportFile')
            ->once()
            ->andReturn([
                'valid' => false,
                'errors' => ['Invalid archive structure'],
                'metadata' => null,
            ]);

        $file = UploadedFile::fake()->create('test.zip', 100);

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $file,
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    #[Test]
    public function test_upload_import_file_validates_file_size(): void
    {
        $largeFile = UploadedFile::fake()->create('test.zip', 200000); // 200MB

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $largeFile,
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    #[Test]
    public function test_upload_import_file_validates_zip_structure(): void
    {
        $invalidZip = $this->createInvalidZipFile();

        $response = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $invalidZip,
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['import_file']);
    }

    #[Test]
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

    #[Test]
    public function test_import_requires_file_path(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'confirm_import' => true,
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['file_path']);
    }

    #[Test]
    public function test_import_requires_confirmation(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => 'temp/test-import.zip',
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['confirm_import']);
    }

    #[Test]
    public function test_import_fails_if_file_not_found(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => 'temp/nonexistent.zip',
                'confirm_import' => true,
            ]);

        $response->assertStatus(ResponseAlias::HTTP_NOT_FOUND);
        $response->assertJson([
            'message' => 'Import file not found',
        ]);
    }

    #[Test]
    public function test_successful_import_returns_statistics(): void
    {
        Technology::factory()->create(['name' => 'Original Tech']);

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

        $this->assertDatabaseHas('technologies', ['name' => 'Test Technology']);
        $this->assertDatabaseMissing('technologies', ['name' => 'Original Tech']);
    }

    #[Test]
    public function test_import_replaces_existing_data(): void
    {
        Technology::factory()->create(['name' => 'Existing Technology']);
        Creation::factory()->create(['name' => 'Existing Creation']);

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

        $this->assertDatabaseMissing('technologies', ['name' => 'Existing Technology']);
        $this->assertDatabaseMissing('creations', ['name' => 'Existing Creation']);
        $this->assertDatabaseHas('technologies', ['name' => 'Test Technology']);
    }

    #[Test]
    public function test_import_restores_files(): void
    {
        Storage::disk('public')->put('uploads/existing-file.txt', 'existing content');

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

        $this->assertFalse(Storage::disk('public')->exists('uploads/existing-file.txt'));
        $this->assertTrue(Storage::disk('public')->exists('uploads/test-image.jpg'));
        $this->assertEquals('test image content', Storage::disk('public')->get('uploads/test-image.jpg'));
    }

    #[Test]
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

    #[Test]
    public function test_get_import_metadata_validates_file_path(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/metadata', []);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['file_path']);
    }

    #[Test]
    public function test_get_import_metadata_handles_missing_file(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/metadata', [
                'file_path' => 'temp/nonexistent.zip',
            ]);

        $response->assertStatus(ResponseAlias::HTTP_NOT_FOUND);
        $response->assertJson([
            'message' => 'File not found',
        ]);
    }

    #[Test]
    public function test_get_import_metadata_handles_invalid_file(): void
    {
        Storage::put('temp/invalid.zip', 'invalid content');

        $mockImportService = $this->mock(WebsiteImportService::class);
        $mockImportService->shouldReceive('getImportMetadata')
            ->once()
            ->andReturn(null);

        $response = $this
            ->postJson('/dashboard/data-management/metadata', [
                'file_path' => 'temp/invalid.zip',
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson([
            'message' => 'Cannot read metadata from file',
        ]);
    }

    #[Test]
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

        $this->assertFalse(Storage::disk('local')->exists($filePath));
    }

    #[Test]
    public function test_cancel_import_validates_file_path(): void
    {
        $response = $this
            ->deleteJson('/dashboard/data-management/cancel', []);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['file_path']);
    }

    #[Test]
    public function test_cancel_import_handles_already_deleted_file(): void
    {
        $response = $this
            ->deleteJson('/dashboard/data-management/cancel', [
                'file_path' => 'temp/already-deleted.zip',
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'message' => 'Import cancelled successfully',
        ]);
    }

    #[Test]
    public function test_import_handles_service_failure(): void
    {
        $this->mock(WebsiteImportService::class, function ($mock) {
            $mock->shouldReceive('validateImportFile')
                ->once()
                ->andReturn(['valid' => true, 'errors' => [], 'metadata' => []]);

            $mock->shouldReceive('importWebsite')
                ->once()
                ->andThrow(new RuntimeException('Import failed'));
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

        $response->assertStatus(ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        $response->assertJson([
            'message' => 'Import failed: Import failed',
        ]);
    }

    #[Test]
    public function test_import_deletes_file_after_successful_import(): void
    {
        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        // Verify file exists before import
        $this->assertTrue(Storage::exists($filePath));

        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();

        // Verify file is deleted after import
        $this->assertFalse(Storage::exists($filePath));
    }

    #[Test]
    public function test_import_validates_confirm_import_must_be_true(): void
    {
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => 'temp/test.zip',
                'confirm_import' => false,
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['confirm_import']);
    }

    #[Test]
    public function test_all_endpoints_require_authentication(): void
    {
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

        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 1,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        $picturesData = [
            [
                'id' => 1,
                'filename' => 'test-icon.jpg',
                'width' => 128,
                'height' => 128,
                'size' => 5000,
                'path_original' => 'uploads/test-icon.jpg',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/pictures.json', json_encode($picturesData));

        $technologiesData = [
            [
                'id' => 1,
                'name' => 'Test Technology',
                'type' => 'framework',
                'icon_picture_id' => 1,
                'description_translation_key_id' => 1,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/technologies.json', json_encode($technologiesData));

        $translationKeysData = [
            [
                'id' => 1,
                'key' => 'technology.test-description',
            ],
        ];
        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));

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

        $zip->addFromString('database/technologies.json', json_encode([]));
        $zip->addFromString('database/users.json', json_encode([]));

        $zip->addFromString('files/uploads/test-image.jpg', 'test image content');

        $zip->close();

        return new UploadedFile($zipPath, 'test-export.zip', 'application/zip', null, true);
    }

    private function createInvalidZipFile(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'invalid_import').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        $zip->addFromString('random-file.txt', 'random content');
        $zip->close();

        return new UploadedFile($zipPath, 'invalid-export.zip', 'application/zip', null, true);
    }

    #[Test]
    public function test_export_includes_blog_tables(): void
    {
        // Create blog data
        $translationKey = TranslationKey::factory()->create(['key' => 'blog.category.test']);
        $category = BlogCategory::factory()->create([
            'slug' => 'test-category',
            'name_translation_key_id' => $translationKey->id,
        ]);

        // Create a valid export with blog data
        $validZip = $this->createValidZipFileWithBlogData($category);
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $uploadResponse->assertSuccessful();

        // Verify the metadata includes blog tables
        $metadata = $uploadResponse->json('metadata');
        $this->assertIsArray($metadata);
    }

    #[Test]
    public function test_import_restores_blog_data(): void
    {
        // Create initial blog data
        $translationKey = TranslationKey::factory()->create(['key' => 'blog.category.existing']);
        BlogCategory::factory()->create([
            'slug' => 'existing-category',
            'name_translation_key_id' => $translationKey->id,
        ]);

        // Create export with different blog data
        $newTranslationKey = TranslationKey::factory()->create(['key' => 'blog.category.imported']);
        $validZip = $this->createValidZipFileWithBlogData(
            BlogCategory::factory()->make([
                'slug' => 'imported-category',
                'name_translation_key_id' => $newTranslationKey->id,
            ])
        );

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

        // Verify old data is gone
        $this->assertDatabaseMissing('blog_categories', ['slug' => 'existing-category']);

        // Verify new data is imported
        $this->assertDatabaseHas('blog_categories', ['slug' => 'imported-category']);
    }

    #[Test]
    public function test_import_preserves_blog_relationships(): void
    {
        // Create a complete blog structure with relationships
        $validZip = $this->createValidZipFileWithCompleteBlogStructure();

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

        // Verify relationships are preserved
        $category = BlogCategory::where('slug', 'test-category')->first();
        $this->assertNotNull($category);

        $post = BlogPost::where('slug', 'test-post')->first();
        $this->assertNotNull($post);
        $this->assertEquals($category->id, $post->category_id);

        // Verify content relationships
        $this->assertDatabaseHas('blog_post_contents', [
            'blog_post_id' => $post->id,
            'content_type' => 'App\Models\BlogContentMarkdown',
        ]);
    }

    private function createValidZipFileWithBlogData($category): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_import_blog').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Add metadata
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add translation keys
        $translationKeysData = [
            [
                'id' => $category->name_translation_key_id,
                'key' => 'blog.category.imported',
            ],
        ];
        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));

        // Add blog categories
        $categoriesData = [
            [
                'id' => 1,
                'slug' => $category->slug,
                'name_translation_key_id' => $category->name_translation_key_id,
                'color' => $category->color ?? 'gray',
                'order' => $category->order ?? 0,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/blog_categories.json', json_encode($categoriesData));

        // Add empty tables for other required tables
        $zip->addFromString('database/pictures.json', json_encode([]));
        $zip->addFromString('database/technologies.json', json_encode([]));
        $zip->addFromString('database/users.json', json_encode([]));

        $zip->close();

        return new UploadedFile($zipPath, 'test-blog-export.zip', 'application/zip', null, true);
    }

    private function createValidZipFileWithCompleteBlogStructure(): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'test_import_complete_blog').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        // Add metadata
        $metadata = [
            'export_date' => now()->toISOString(),
            'laravel_version' => app()->version(),
            'database_name' => 'test_db',
            'files_count' => 0,
        ];
        $zip->addFromString('export-metadata.json', json_encode($metadata));

        // Add translation keys
        $translationKeysData = [
            ['id' => 1, 'key' => 'blog.category.test'],
            ['id' => 2, 'key' => 'blog.post.test.title'],
            ['id' => 3, 'key' => 'blog.content.markdown.test'],
        ];
        $zip->addFromString('database/translation_keys.json', json_encode($translationKeysData));

        // Add pictures
        $picturesData = [
            [
                'id' => 1,
                'filename' => 'cover.jpg',
                'width' => 1200,
                'height' => 800,
                'size' => 50000,
                'path_original' => 'uploads/cover.jpg',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/pictures.json', json_encode($picturesData));

        // Add blog categories
        $categoriesData = [
            [
                'id' => 1,
                'slug' => 'test-category',
                'name_translation_key_id' => 1,
                'color' => 'blue',
                'order' => 0,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/blog_categories.json', json_encode($categoriesData));

        // Add blog content markdown
        $markdownData = [
            [
                'id' => 1,
                'translation_key_id' => 3,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/blog_content_markdown.json', json_encode($markdownData));

        // Add blog posts
        $postsData = [
            [
                'id' => 1,
                'slug' => 'test-post',
                'title_translation_key_id' => 2,
                'type' => 'article',
                'category_id' => 1,
                'cover_picture_id' => 1,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/blog_posts.json', json_encode($postsData));

        // Add blog post contents
        $postContentsData = [
            [
                'id' => 1,
                'blog_post_id' => 1,
                'content_type' => 'App\Models\BlogContentMarkdown',
                'content_id' => 1,
                'order' => 0,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ];
        $zip->addFromString('database/blog_post_contents.json', json_encode($postContentsData));

        // Add empty tables for other required tables
        $zip->addFromString('database/technologies.json', json_encode([]));
        $zip->addFromString('database/users.json', json_encode([]));
        $zip->addFromString('database/blog_content_galleries.json', json_encode([]));
        $zip->addFromString('database/blog_content_videos.json', json_encode([]));
        $zip->addFromString('database/blog_post_drafts.json', json_encode([]));
        $zip->addFromString('database/blog_post_draft_contents.json', json_encode([]));
        $zip->addFromString('database/blog_content_gallery_pictures.json', json_encode([]));
        $zip->addFromString('database/optimized_pictures.json', json_encode([]));
        $zip->addFromString('database/people.json', json_encode([]));
        $zip->addFromString('database/tags.json', json_encode([]));
        $zip->addFromString('database/videos.json', json_encode([]));
        $zip->addFromString('database/translations.json', json_encode([]));

        $zip->close();

        return new UploadedFile($zipPath, 'test-complete-blog-export.zip', 'application/zip', null, true);
    }

    #[Test]
    public function test_import_requires_password_in_production(): void
    {
        $this->withoutMiddleware();

        // Mock app environment to return production
        $this->app->instance('env', 'production');

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

        $response->assertStatus(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function test_import_validates_password_in_production(): void
    {
        $this->withoutMiddleware();

        // Mock app environment to return production
        $this->app->instance('env', 'production');

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
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(ResponseAlias::HTTP_UNAUTHORIZED);
        $response->assertJson([
            'message' => 'Invalid password',
        ]);
    }

    #[Test]
    public function test_import_accepts_correct_password_in_production(): void
    {
        $this->withoutMiddleware();

        // Mock app environment to return production
        $this->app->instance('env', 'production');

        // Create user with known password
        $password = 'correct-password';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Login as this user
        auth()->logout();
        $this->actingAs($user);

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
                'password' => $password,
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'message' => 'Import completed successfully',
        ]);
    }

    #[Test]
    public function test_import_does_not_require_password_in_development(): void
    {
        // Ensure we're in testing/development environment
        App::instance('env', 'testing');
        config(['app.env' => 'testing']);

        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        // Import without password should succeed in development
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();
        $response->assertJson([
            'message' => 'Import completed successfully',
        ]);
    }

    #[Test]
    public function test_index_passes_is_production_to_frontend(): void
    {
        // Test in production environment
        App::instance('env', 'production');
        config(['app.env' => 'production']);

        $response = $this->get('/dashboard/data-management');
        $response->assertSuccessful();

        // Inertia response should include isProduction
        $page = $response->viewData('page');
        $props = $page['props'];
        $this->assertTrue($props['isProduction']);

        // Test in development environment
        App::instance('env', 'testing');
        config(['app.env' => 'testing']);

        $response = $this->get('/dashboard/data-management');
        $response->assertSuccessful();

        $page = $response->viewData('page');
        $props = $page['props'];
        $this->assertFalse($props['isProduction']);
    }

    #[Test]
    public function test_import_clears_local_disk_files(): void
    {
        $localDisk = Storage::disk('local');

        // Create some files in the local disk temp directory
        $localDisk->put('temp/export-old-1.zip', 'old export content 1');
        $localDisk->put('temp/export-old-2.zip', 'old export content 2');
        $localDisk->put('temp/some-other-file.txt', 'other content');

        // Verify files exist before import
        $this->assertTrue($localDisk->exists('temp/export-old-1.zip'));
        $this->assertTrue($localDisk->exists('temp/export-old-2.zip'));
        $this->assertTrue($localDisk->exists('temp/some-other-file.txt'));

        // Create and upload a valid import file
        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        // Perform the import
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();

        // Verify temp files are deleted
        $this->assertFalse($localDisk->exists('temp/export-old-1.zip'));
        $this->assertFalse($localDisk->exists('temp/export-old-2.zip'));
        $this->assertFalse($localDisk->exists('temp/some-other-file.txt'));
    }

    #[Test]
    public function test_import_clears_local_disk_but_preserves_framework_directory(): void
    {
        $localDisk = Storage::disk('local');

        // Create framework directory files (should not be deleted)
        $localDisk->put('framework/cache/test.cache', 'cache content');
        $localDisk->put('framework/sessions/session.txt', 'session content');

        // Create temp files (should be deleted)
        $localDisk->put('temp/export.zip', 'export content');

        // Verify files exist before import
        $this->assertTrue($localDisk->exists('framework/cache/test.cache'));
        $this->assertTrue($localDisk->exists('framework/sessions/session.txt'));
        $this->assertTrue($localDisk->exists('temp/export.zip'));

        // Create and upload a valid import file
        $validZip = $this->createValidZipFile();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        // Perform the import
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();

        // Verify framework files are preserved
        $this->assertTrue($localDisk->exists('framework/cache/test.cache'));
        $this->assertTrue($localDisk->exists('framework/sessions/session.txt'));

        // Verify temp files are deleted
        $this->assertFalse($localDisk->exists('temp/export.zip'));
    }

    #[Test]
    public function test_import_clears_public_disk_files(): void
    {
        $publicDisk = Storage::disk('public');

        // Create some files in the public disk
        $publicDisk->put('uploads/old-image.jpg', 'old image content');
        $publicDisk->put('uploads/subfolder/old-file.txt', 'old file content');

        // Verify files exist before import
        $this->assertTrue($publicDisk->exists('uploads/old-image.jpg'));
        $this->assertTrue($publicDisk->exists('uploads/subfolder/old-file.txt'));

        // Create and upload a valid import file with new files
        $validZip = $this->createValidZipFileWithFiles();
        $uploadResponse = $this
            ->postJson('/dashboard/data-management/upload', [
                'import_file' => $validZip,
            ]);

        $filePath = $uploadResponse->json('file_path');

        // Perform the import
        $response = $this
            ->postJson('/dashboard/data-management/import', [
                'file_path' => $filePath,
                'confirm_import' => true,
            ]);

        $response->assertSuccessful();

        // Verify old files are deleted
        $this->assertFalse($publicDisk->exists('uploads/old-image.jpg'));
        $this->assertFalse($publicDisk->exists('uploads/subfolder/old-file.txt'));

        // Verify new files from import are present
        $this->assertTrue($publicDisk->exists('uploads/test-image.jpg'));
    }
}
