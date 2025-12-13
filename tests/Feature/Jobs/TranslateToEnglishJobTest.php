<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TranslateToEnglishJob;
use App\Models\TranslationKey;
use App\Services\AI\AiTextPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ActsAsUser;

#[CoversClass(TranslateToEnglishJob::class)]
class TranslateToEnglishJobTest extends TestCase
{
    use ActsAsUser, RefreshDatabase;

    #[Test]
    public function test_job_creates_english_translation_from_french()
    {
        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $frenchTranslation = $key->translations()->create([
            'locale' => 'fr',
            'text' => 'Bonjour le monde',
        ]);

        $mockAiService = Mockery::mock(AiTextPromptService::class);
        $mockAiService->shouldReceive('prompt')
            ->once()
            ->with(
                'You are a helpful assistant that translates french markdown text in english and that outputs JSON in the format {message:string}. Markdown is supported.',
                'Translate this French text to English: Bonjour le monde'
            )
            ->andReturn(['message' => 'Hello world']);

        $this->app->instance(AiTextPromptService::class, $mockAiService);

        $job = new TranslateToEnglishJob($key->id);
        $job->handle($mockAiService);

        $this->assertDatabaseHas('translations', [
            'translation_key_id' => $key->id,
            'locale' => 'en',
            'text' => 'Hello world',
        ]);
    }

    #[Test]
    public function test_job_skips_if_english_translation_already_exists()
    {
        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);
        $key->translations()->create(['locale' => 'en', 'text' => 'Hello']);

        $mockAiService = Mockery::mock(AiTextPromptService::class);
        $mockAiService->shouldNotReceive('prompt');

        $job = new TranslateToEnglishJob($key->id);
        $job->handle($mockAiService);

        // Should still have only one English translation
        $this->assertCount(1, $key->translations()->where('locale', 'en')->get());
    }

    #[Test]
    public function test_job_skips_if_no_french_translation_exists()
    {
        $key = TranslationKey::factory()->create(['key' => 'test.hello']);

        $mockAiService = Mockery::mock(AiTextPromptService::class);
        $mockAiService->shouldNotReceive('prompt');

        $job = new TranslateToEnglishJob($key->id);
        $job->handle($mockAiService);

        // Should not have created any English translation
        $this->assertCount(0, $key->translations()->where('locale', 'en')->get());
    }

    #[Test]
    public function test_job_handles_nonexistent_translation_key()
    {
        $mockAiService = Mockery::mock(AiTextPromptService::class);
        $mockAiService->shouldNotReceive('prompt');

        $job = new TranslateToEnglishJob(999999);
        $job->handle($mockAiService);

        // Should not throw an exception and should handle gracefully
        $this->assertTrue(true);
    }

    #[Test]
    public function test_job_constructor_sets_properties()
    {
        $job = new TranslateToEnglishJob(123, true);

        $this->assertEquals(123, $job->translationKeyId);
        $this->assertTrue($job->overwrite);

        $job2 = new TranslateToEnglishJob(456);
        $this->assertEquals(456, $job2->translationKeyId);
        $this->assertFalse($job2->overwrite);
    }

    #[Test]
    public function test_job_overwrites_existing_english_translation_when_overwrite_is_true()
    {
        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $frenchTranslation = $key->translations()->create([
            'locale' => 'fr',
            'text' => 'Bonjour le monde',
        ]);
        $existingEnglish = $key->translations()->create([
            'locale' => 'en',
            'text' => 'Old Hello World',
        ]);

        $mockAiService = Mockery::mock(AiTextPromptService::class);
        $mockAiService->shouldReceive('prompt')
            ->once()
            ->with(
                'You are a helpful assistant that translates french markdown text in english and that outputs JSON in the format {message:string}. Markdown is supported.',
                'Translate this French text to English: Bonjour le monde'
            )
            ->andReturn(['message' => 'New Hello World']);

        $job = new TranslateToEnglishJob($key->id, true);
        $job->handle($mockAiService);

        $this->assertDatabaseHas('translations', [
            'id' => $existingEnglish->id,
            'translation_key_id' => $key->id,
            'locale' => 'en',
            'text' => 'New Hello World',
        ]);

        // Should still have only one English translation
        $this->assertCount(1, $key->translations()->where('locale', 'en')->get());
    }

    #[Test]
    public function test_job_throws_exception_when_ai_service_returns_invalid_response()
    {
        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);

        $mockAiService = Mockery::mock(AiTextPromptService::class);
        $mockAiService->shouldReceive('prompt')
            ->once()
            ->andReturn(['invalid' => 'response']);

        $job = new TranslateToEnglishJob($key->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid response format from AI service');

        $job->handle($mockAiService);
    }

    #[Test]
    public function test_job_rethrows_ai_service_exceptions()
    {
        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);

        $mockAiService = Mockery::mock(AiTextPromptService::class);
        $mockAiService->shouldReceive('prompt')
            ->once()
            ->andThrow(new \Exception('AI service error'));

        $job = new TranslateToEnglishJob($key->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AI service error');

        $job->handle($mockAiService);
    }
}
