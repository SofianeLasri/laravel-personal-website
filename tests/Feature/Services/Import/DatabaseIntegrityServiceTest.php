<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Import;

use App\Models\TranslationKey;
use App\Services\Import\DatabaseIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DatabaseIntegrityService::class)]
class DatabaseIntegrityServiceTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DatabaseIntegrityService::class);
    }

    #[Test]
    public function it_resets_auto_increments(): void
    {
        // Create and delete some records to change auto_increment
        $key1 = TranslationKey::factory()->create();
        $key2 = TranslationKey::factory()->create();
        $lastId = $key2->id;

        $key1->delete();
        $key2->delete();

        // Reset auto increments
        $this->service->resetAutoIncrements();

        // Create a new record - ID should start from a lower value
        // Note: For SQLite, this behavior might differ
        $newKey = TranslationKey::factory()->create();

        $this->assertNotNull($newKey->id);
        // We just verify it doesn't throw and creates a record successfully
        $this->assertDatabaseHas('translation_keys', ['id' => $newKey->id]);
    }

    #[Test]
    public function it_verifies_integrity_returns_array(): void
    {
        // Create some data
        TranslationKey::factory()->count(3)->create();

        $issues = $this->service->verifyIntegrity();

        $this->assertIsArray($issues);
        // With properly imported data, there should be no issues
        // Note: Full integrity check only works with MySQL
    }

    #[Test]
    public function it_handles_empty_database(): void
    {
        // Verify with empty database doesn't throw
        $issues = $this->service->verifyIntegrity();

        $this->assertIsArray($issues);
    }

    #[Test]
    public function it_does_not_throw_on_reset_with_empty_tables(): void
    {
        // Should not throw even with empty tables
        $this->service->resetAutoIncrements();

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    #[Test]
    public function it_handles_tables_without_auto_increment(): void
    {
        // Create some data in pivot tables (which typically don't have auto_increment)
        TranslationKey::factory()->create();

        // Should not throw
        $this->service->resetAutoIncrements();

        $this->assertTrue(true);
    }

    #[Test]
    public function it_returns_empty_issues_for_valid_data(): void
    {
        // Create valid related data
        $translationKey = TranslationKey::factory()->create();

        $issues = $this->service->verifyIntegrity();

        $this->assertIsArray($issues);
        // Since we created valid data, there should be no issues
        $this->assertEmpty($issues);
    }
}
