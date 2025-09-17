<?php

namespace Tests\Feature\Services;

use App\Models\Picture;
use App\Services\AiProviderService;
use App\Services\ImageTranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

    private array $sampleOpenAiResponse = [
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

    private array $sampleAnthropicResponse = [
        'id' => 'msg_1',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-20250514',
        'content' => [
            [
                'type' => 'text',
                'text' => "{\n  \"message\": \"Why don't scientists trust atoms? Because they make up everything!\"\n}",
            ],
        ],
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'usage' => [
            'input_tokens' => 33,
            'output_tokens' => 20,
        ],
    ];

    public function test_prompt_with_pictures_sends_correct_request_openai()
    {
        Storage::fake('public');
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(json_encode($this->sampleOpenAiResponse)),
        ]);

        $mockTranscodingService = Mockery::mock(ImageTranscodingService::class);
        $mockTranscodingService->shouldReceive('transcode')
            ->andReturn('transcoded-image-content');
        App::instance(ImageTranscodingService::class, $mockTranscodingService);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
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

    public function test_prompt_with_pictures_sends_correct_request_anthropic()
    {
        Storage::fake('public');
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(json_encode($this->sampleAnthropicResponse)),
        ]);

        $mockTranscodingService = Mockery::mock(ImageTranscodingService::class);
        $mockTranscodingService->shouldReceive('transcode')
            ->andReturn('transcoded-image-content');
        App::instance(ImageTranscodingService::class, $mockTranscodingService);

        Config::set('ai-provider.selected-provider', 'anthropic');
        Config::set('ai-provider.providers.anthropic', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-sonnet-4-20250514',
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

    public function test_prompt_sends_correct_request_openai()
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(json_encode($this->sampleOpenAiResponse)),
        ]);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $response = $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );

        $this->assertEquals(['message' => "Why don't scientists trust atoms? Because they make up everything!"], $response);
    }

    public function test_prompt_sends_correct_request_anthropic()
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(json_encode($this->sampleAnthropicResponse)),
        ]);

        Config::set('ai-provider.selected-provider', 'anthropic');
        Config::set('ai-provider.providers.anthropic', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-sonnet-4-20250514',
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
            'https://api.openai.com/v1/chat/completions' => Http::response(json_encode($this->sampleOpenAiResponse)),
        ]);

        $mockTranscodingService = Mockery::mock(ImageTranscodingService::class);
        $mockTranscodingService->shouldReceive('transcode')
            ->andThrow(new \App\Exceptions\ImageTranscodingException(
                \App\Enums\ImageTranscodingError::IMAGICK_ENCODING_FAILED,
                'imagick',
                'Test transcoding failure'
            ));
        App::instance(ImageTranscodingService::class, $mockTranscodingService);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

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

    public function test_prompt_handles_api_failure_openai()
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response('Error', 500),
        ]);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_api_failure_anthropic()
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response('Error', 500),
        ]);

        Config::set('ai-provider.selected-provider', 'anthropic');
        Config::set('ai-provider.providers.anthropic', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-sonnet-4-20250514',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_connection_exception_openai()
    {
        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to call AI provider API');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_connection_exception_anthropic()
    {
        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        Config::set('ai-provider.selected-provider', 'anthropic');
        Config::set('ai-provider.providers.anthropic', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-sonnet-4-20250514',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to call AI provider API');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_malformed_api_response_missing_choices_openai()
    {
        $malformedResponse = [
            'id' => 'chat_1',
            'object' => 'chat.completion',
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(json_encode($malformedResponse)),
        ]);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from AI provider');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_malformed_api_response_missing_content_anthropic()
    {
        $malformedResponse = [
            'id' => 'msg_1',
            'type' => 'message',
        ];

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(json_encode($malformedResponse)),
        ]);

        Config::set('ai-provider.selected-provider', 'anthropic');
        Config::set('ai-provider.providers.anthropic', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-sonnet-4-20250514',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from AI provider');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_malformed_api_response_missing_message_openai()
    {
        $malformedResponse = [
            'id' => 'chat_1',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                ],
            ],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(json_encode($malformedResponse)),
        ]);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from AI provider');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_malformed_api_response_missing_content_openai()
    {
        $malformedResponse = [
            'id' => 'chat_1',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(json_encode($malformedResponse)),
        ]);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from AI provider');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_invalid_json_in_content_openai()
    {
        $responseWithInvalidJson = [
            'id' => 'chat_1',
            'object' => 'chat.completion',
            'created' => 1739718124,
            'model' => 'gpt-4o-mini',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This is not valid JSON content',
                        'refusal' => null,
                    ],
                    'logprobs' => null,
                    'finish_reason' => 'stop',
                ],
            ],
        ];

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(json_encode($responseWithInvalidJson)),
        ]);

        Config::set('ai-provider.selected-provider', 'openai');
        Config::set('ai-provider.providers.openai', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-mini',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider returned invalid JSON content');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }

    public function test_prompt_handles_invalid_json_in_content_anthropic()
    {
        $responseWithInvalidJson = [
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'This is not valid JSON content',
                ],
            ],
            'stop_reason' => 'end_turn',
        ];

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(json_encode($responseWithInvalidJson)),
        ]);

        Config::set('ai-provider.selected-provider', 'anthropic');
        Config::set('ai-provider.providers.anthropic', [
            'api-key' => 'test-api-key',
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-sonnet-4-20250514',
            'max-tokens' => 100,
        ]);

        $service = new AiProviderService;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider returned invalid JSON content');

        $service->prompt(
            'You are a helpful assistant.',
            'Tell me a joke.'
        );
    }
}
