<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Translation;

use App\Models\TranslationKey;
use App\Services\Translation\TranslationKeyGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TranslationKeyGeneratorService::class)]
class TranslationKeyGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private TranslationKeyGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TranslationKeyGeneratorService::class);
    }

    #[Test]
    public function it_generates_unique_key_with_suffix(): void
    {
        $originalKey = 'my_translation_key';

        $result = $this->service->generate($originalKey, 'copy');

        $this->assertEquals('my_translation_key_copy', $result);
    }

    #[Test]
    public function it_increments_counter_when_key_exists(): void
    {
        $originalKey = 'existing_key';

        // Create the first key that would conflict
        TranslationKey::factory()->create(['key' => 'existing_key_copy']);

        $result = $this->service->generate($originalKey, 'copy');

        $this->assertEquals('existing_key_copy_1', $result);
    }

    #[Test]
    public function it_handles_multiple_existing_keys(): void
    {
        $originalKey = 'popular_key';

        // Create multiple conflicting keys
        TranslationKey::factory()->create(['key' => 'popular_key_copy']);
        TranslationKey::factory()->create(['key' => 'popular_key_copy_1']);
        TranslationKey::factory()->create(['key' => 'popular_key_copy_2']);

        $result = $this->service->generate($originalKey, 'copy');

        $this->assertEquals('popular_key_copy_3', $result);
    }

    #[Test]
    public function it_generates_for_draft_with_draft_suffix(): void
    {
        $originalKey = 'draft_test_key';

        $result = $this->service->generateForDraft($originalKey);

        $this->assertEquals('draft_test_key_draft', $result);
    }

    #[Test]
    public function it_generates_for_copy_with_copy_suffix(): void
    {
        $originalKey = 'copy_test_key';

        $result = $this->service->generateForCopy($originalKey);

        $this->assertEquals('copy_test_key_copy', $result);
    }

    #[Test]
    public function it_generates_unique_key_when_original_has_same_suffix(): void
    {
        $originalKey = 'key_copy';

        $result = $this->service->generate($originalKey, 'copy');

        $this->assertEquals('key_copy_copy', $result);
    }

    #[Test]
    public function it_handles_empty_suffix(): void
    {
        $originalKey = 'test_key';

        $result = $this->service->generate($originalKey, '');

        $this->assertEquals('test_key_', $result);
    }

    #[Test]
    public function it_handles_special_characters_in_key(): void
    {
        $originalKey = 'key-with.special_chars';

        $result = $this->service->generate($originalKey, 'copy');

        $this->assertEquals('key-with.special_chars_copy', $result);
    }
}
