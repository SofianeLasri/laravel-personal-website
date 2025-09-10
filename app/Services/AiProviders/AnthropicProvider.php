<?php

namespace App\Services\AiProviders;

use App\Contracts\AiProviderInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProviderInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function prompt(string $systemRole, string $userPrompt): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->config['api-key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->config['url'], [
            'model' => $this->config['model'],
            'system' => $systemRole,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => $this->config['max-tokens'],
        ]);

        if (! $response->successful()) {
            throw new Exception('Anthropic API error: '.$response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function promptWithImages(string $systemRole, string $userPrompt, array $images): array
    {
        $content = [
            ['type' => 'text', 'text' => $userPrompt],
        ];

        foreach ($images as $image) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $image['mime_type'],
                    'data' => $image['base64'],
                ],
            ];
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->config['api-key'],
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->config['url'], [
            'model' => $this->config['model'],
            'system' => $systemRole,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'max_tokens' => $this->config['max-tokens'],
        ]);

        if (! $response->successful()) {
            throw new Exception('Anthropic API error: '.$response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(): bool
    {
        $cacheKey = 'provider_health_anthropic';

        return Cache::remember($cacheKey, 300, function () {
            try {
                // Anthropic doesn't have a models endpoint, so we test with a minimal prompt
                $response = Http::withHeaders([
                    'x-api-key' => $this->config['api-key'],
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])->timeout(5)->post($this->config['url'], [
                    'model' => 'claude-3-haiku-20240307', // Cheapest model for health check
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hi'],
                    ],
                    'max_tokens' => 1,
                ]);

                if (! $response->successful()) {
                    Log::warning('Anthropic health check failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return false;
                }

                return true;
            } catch (Exception $e) {
                Log::warning('Anthropic health check exception', [
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'anthropic';
    }

    /**
     * {@inheritDoc}
     */
    public function getModel(): string
    {
        return $this->config['model'] ?? 'claude-3-5-sonnet-20241022';
    }

    /**
     * {@inheritDoc}
     */
    public function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $pricing = $this->config['pricing'] ?? [
            'input_per_1k' => 0.003,
            'output_per_1k' => 0.015,
        ];

        $inputCost = ($inputTokens / 1000) * $pricing['input_per_1k'];
        $outputCost = ($outputTokens / 1000) * $pricing['output_per_1k'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * {@inheritDoc}
     */
    public function getEndpoint(): string
    {
        return $this->config['url'] ?? 'https://api.anthropic.com/v1/messages';
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxTokens(): int
    {
        return $this->config['max-tokens'] ?? 4096;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
