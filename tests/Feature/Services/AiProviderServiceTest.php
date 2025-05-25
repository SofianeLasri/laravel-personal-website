<?php

namespace Services;

use App\Models\Picture;
use App\Services\AiProviderService;
use App\Services\ImageTranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(AiProviderService::class)]
class AiProviderServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $sampleResponse = [
        'id' => 'chat_1',
        'object' => 'chat.completion',
        'created' => 1739718124,
        'model' => 'gpt-4o-mini',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => "{\n  \"message\": \"Why don't scientists trust atoms? Because they make up everything!\"\n}",
                    'refusal' => null,
                ],
                'logprobs' => null,
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 33,
            'completion_tokens' => 20,
            'total_tokens' => 53,
            'prompt_tokens_details' => [
                'cached_tokens' => 0,
                'audio_tokens' => 0,
            ],
            'completion_tokens_details' => [
                'reasoning_tokens' => 0,
                'audio_tokens' => 0,
                'accepted_prediction_tokens' => 0,
                'rejected_prediction_tokens' => 0,
            ],
        ],
        'service_tier' => 'default',
        'system_fingerprint' => 'system-1',
    ];

    public function test_prompt_with_pictures_sends_correct_request()
    {
        Storage::fake('public');
        Http::fake([
            'https://api.test-provider.com' => Http::response(json_encode($this->sampleResponse)),
        ]);

        $mockTranscodingService = Mockery::mock(ImageTranscodingService::class);
        $mockTranscodingService->shouldReceive('transcode')
            ->andReturn('transcoded-image-content');
        App::instance(ImageTranscodingService::class, $mockTranscodingService);

        Config::set('ai-provider.selected-provider', 'test-provider');
        Config::set('ai-provider.providers.test-provider', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.test-provider.com',
            'model' => 'test-model',
            'max-tokens' => 100,
        ]);

        $Picture = Picture::factory()->create();
        $service = new AiProviderService;

        $response = $service->promptWithPictures(
            'You are a helpful assistant.',
            'Describe this image.',
            $Picture
        );

        $this->assertEquals(['message' => "Why don't scientists trust atoms? Because they make up everything!"], $response);
    }

    public function test_prompt_sends_correct_request()
    {
        Http::fake([
            'https://api.test-provider.com' => Http::response(json_encode($this->sampleResponse)),
        ]);

        Config::set('ai-provider.selected-provider', 'test-provider');
        Config::set('ai-provider.providers.test-provider', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.test-provider.com',
            'model' => 'test-model',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $response = $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );

        $this->assertEquals(['message' => "Why don't scientists trust atoms? Because they make up everything!"], $response);
    }

    public function test_prompt_with_pictures_handles_transcoding_failure()
    {
        Storage::fake('public');
        Http::fake([
            'https://api.test-provider.com' => Http::response(json_encode($this->sampleResponse)),
        ]);

        $mockTranscodingService = Mockery::mock(ImageTranscodingService::class);
        $mockTranscodingService->shouldReceive('transcode')
            ->andReturn(null);
        App::instance(ImageTranscodingService::class, $mockTranscodingService);

        $Picture = Picture::factory()->create();
        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to transcode picture');

        $service->promptWithPictures(
            'You are a helpful assistant.',
            'Describe this image.',
            $Picture
        );
    }

    public function test_prompt_with_pictures_handles_null_path_original()
    {
        Storage::fake('public');

        $picture = Picture::factory()->create(['path_original' => null]);
        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Picture has no original path');

        $service->promptWithPictures(
            'You are a helpful assistant.',
            'Describe this image.',
            $picture
        );
    }

    public function test_prompt_with_pictures_handles_storage_failure()
    {
        Storage::fake('public');

        $picture = Picture::factory()->create(['path_original' => 'non-existent-file.jpg']);
        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get picture content from storage');

        $service->promptWithPictures(
            'You are a helpful assistant.',
            'Describe this image.',
            $picture
        );
    }

    public function test_prompt_handles_api_failure()
    {
        Http::fake([
            'https://api.test-provider.com' => Http::response('Error', 500),
        ]);

        Config::set('ai-provider.selected-provider', 'test-provider');
        Config::set('ai-provider.providers.test-provider', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.test-provider.com',
            'model' => 'test-model',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }
}
