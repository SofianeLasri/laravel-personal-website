<?php

namespace Tests\Feature\Jobs;

use App\Jobs\TranslateToEnglishJob;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Models\User;
use App\Services\AiProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldReceive('prompt')
            ->once()
            ->with(
                'You are a helpful assistant that translates french markdown text in english and that outputs JSON in the format {message:string}. Markdown is supported.',
                'Translate this French text to English: Bonjour le monde'
            )
            ->andReturn(['message' => 'Hello world']);

        $this->app->instance(AiProviderService::class, $mockAiService);

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

        $mockAiService = Mockery::mock(AiProviderService::class);
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

        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldNotReceive('prompt');

        $job = new TranslateToEnglishJob($key->id);
        $job->handle($mockAiService);

        // Should not have created any English translation
        $this->assertCount(0, $key->translations()->where('locale', 'en')->get());
    }

    #[Test]
    public function test_job_handles_nonexistent_translation_key()
    {
        $mockAiService = Mockery::mock(AiProviderService::class);
        $mockAiService->shouldNotReceive('prompt');

        $job = new TranslateToEnglishJob(999999);
        $job->handle($mockAiService);

        // Should not throw an exception and should handle gracefully
        $this->assertTrue(true);
    }

    #[Test]
    public function test_translate_single_endpoint_queues_job()
    {
        Queue::fake();
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.api.translations.translate-single', $key));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Translation job queued successfully',
            ]);

        Queue::assertPushed(TranslateToEnglishJob::class, function ($job) use ($key) {
            return $job->translationKeyId === $key->id;
        });
    }

    #[Test]
    public function test_translate_single_fails_if_english_already_exists()
    {
        $user = User::factory()->create();

        $key = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);
        $key->translations()->create(['locale' => 'en', 'text' => 'Hello']);

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.api.translations.translate-single', $key));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'English translation already exists',
            ]);
    }

    #[Test]
    public function test_translate_batch_missing_mode()
    {
        Queue::fake();
        $user = User::factory()->create();

        $key1 = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key1->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);

        $key2 = TranslationKey::factory()->create(['key' => 'test.goodbye']);
        $key2->translations()->create(['locale' => 'fr', 'text' => 'Au revoir']);
        $key2->translations()->create(['locale' => 'en', 'text' => 'Goodbye']);

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.api.translations.translate-batch'), [
                'mode' => 'missing',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'jobs_dispatched' => 1,
            ]);

        Queue::assertPushed(TranslateToEnglishJob::class, 1);
    }

    #[Test]
    public function test_translate_batch_all_mode()
    {
        Queue::fake();
        $user = User::factory()->create();

        $key1 = TranslationKey::factory()->create(['key' => 'test.hello']);
        $key1->translations()->create(['locale' => 'fr', 'text' => 'Bonjour']);

        $key2 = TranslationKey::factory()->create(['key' => 'test.goodbye']);
        $key2->translations()->create(['locale' => 'fr', 'text' => 'Au revoir']);
        $key2->translations()->create(['locale' => 'en', 'text' => 'Goodbye']);

        $response = $this->actingAs($user)
            ->postJson(route('dashboard.api.translations.translate-batch'), [
                'mode' => 'all',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'jobs_dispatched' => 2,
            ]);

        Queue::assertPushed(TranslateToEnglishJob::class, 2);
    }
}
