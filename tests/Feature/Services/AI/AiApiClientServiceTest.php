<?php

declare(strict_types=1);

namespace Tests\Feature\Services\AI;

use App\Services\AI\AiApiClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(AiApiClientService::class)]
class AiApiClientServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiApiClientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiApiClientService::class);
    }

    #[Test]
    public function it_calls_openai_api_successfully(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"message": "Hello from OpenAI"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $result = $this->service->callOpenAi($config, 'You are a helpful assistant', 'Hello');

        $this->assertIsArray($result);
        $this->assertEquals('Hello from OpenAI', $result['message']);
    }

    #[Test]
    public function it_calls_openai_api_with_images(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"description": "An image of a cat"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4-vision-preview',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        // Simulate a small image content
        $imageContent = 'fake-image-binary-data';

        $result = $this->service->callOpenAi(
            $config,
            'Describe this image',
            'What do you see?',
            [$imageContent]
        );

        $this->assertIsArray($result);
        $this->assertEquals('An image of a cat', $result['description']);
    }

    #[Test]
    public function it_throws_exception_for_openai_connection_error(): void
    {
        Http::fake([
            'api.openai.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
            },
        ]);

        $config = [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to call AI provider API');

        $this->service->callOpenAi($config, 'System role', 'User prompt');
    }

    #[Test]
    public function it_throws_exception_for_openai_invalid_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => 'Invalid request',
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from AI provider');

        $this->service->callOpenAi($config, 'System role', 'User prompt');
    }

    #[Test]
    public function it_calls_anthropic_api_successfully(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => '{"message": "Hello from Claude"}',
                    ],
                ],
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-3-opus-20240229',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $result = $this->service->callAnthropic($config, 'You are a helpful assistant', 'Hello');

        $this->assertIsArray($result);
        $this->assertEquals('Hello from Claude', $result['message']);
    }

    #[Test]
    public function it_calls_anthropic_api_with_images(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => '{"description": "A beautiful landscape"}',
                    ],
                ],
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-3-opus-20240229',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $imageContent = 'fake-image-binary-data';

        $result = $this->service->callAnthropic(
            $config,
            'Describe this image',
            'What do you see?',
            [$imageContent]
        );

        $this->assertIsArray($result);
        $this->assertEquals('A beautiful landscape', $result['description']);
    }

    #[Test]
    public function it_throws_exception_for_anthropic_connection_error(): void
    {
        Http::fake([
            'api.anthropic.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
            },
        ]);

        $config = [
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-3-opus-20240229',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to call AI provider API');

        $this->service->callAnthropic($config, 'System role', 'User prompt');
    }

    #[Test]
    public function it_throws_exception_for_anthropic_invalid_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'error' => 'Invalid request',
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-3-opus-20240229',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get response from AI provider');

        $this->service->callAnthropic($config, 'System role', 'User prompt');
    }

    #[Test]
    public function it_throws_exception_for_openai_invalid_json_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'not valid json at all @#$%',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider returned invalid JSON content');

        $this->service->callOpenAi($config, 'System role', 'User prompt');
    }

    #[Test]
    public function it_throws_exception_for_anthropic_invalid_json_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => 'completely invalid json !!!',
                    ],
                ],
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-3-opus-20240229',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider returned invalid JSON content');

        $this->service->callAnthropic($config, 'System role', 'User prompt');
    }

    #[Test]
    public function it_handles_complex_json_response_from_openai(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"status": "success", "data": {"id": 1, "items": ["a", "b"]}}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $config = [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4',
            'api-key' => 'test-key',
            'max-tokens' => 1000,
        ];

        $result = $this->service->callOpenAi($config, 'System', 'Prompt');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals(['a', 'b'], $result['data']['items']);
    }
}
