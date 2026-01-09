<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Conversion\Creation;

use App\Models\CreationDraft;
use App\Services\Conversion\Creation\CreationValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationValidationService::class)]
class CreationValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreationValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreationValidationService::class);
    }

    #[Test]
    public function it_validates_complete_draft(): void
    {
        $draft = CreationDraft::factory()->create();

        // Should not throw
        $this->service->validate($draft);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_for_missing_short_description(): void
    {
        $draft = CreationDraft::factory()->create();
        $draft->short_description_translation_key_id = null;

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_throws_exception_for_missing_logo(): void
    {
        $draft = CreationDraft::factory()->create();
        $draft->logo_id = null;

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_throws_exception_for_missing_cover_image(): void
    {
        $draft = CreationDraft::factory()->create();
        $draft->cover_image_id = null;

        $this->expectException(ValidationException::class);

        $this->service->validate($draft);
    }

    #[Test]
    public function it_returns_true_for_valid_draft(): void
    {
        $draft = CreationDraft::factory()->create();

        $this->assertTrue($this->service->isValid($draft));
    }

    #[Test]
    public function it_returns_false_for_invalid_draft(): void
    {
        $draft = CreationDraft::factory()->create();
        $draft->logo_id = null;

        $this->assertFalse($this->service->isValid($draft));
    }

    #[Test]
    public function it_returns_false_when_multiple_fields_missing(): void
    {
        $draft = CreationDraft::factory()->create();
        $draft->short_description_translation_key_id = null;
        $draft->logo_id = null;
        $draft->cover_image_id = null;

        $this->assertFalse($this->service->isValid($draft));
    }
}
