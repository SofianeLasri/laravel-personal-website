<?php

namespace App\Services\AiProviders;

use App\Contracts\AiProviderInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
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
            'Authorization' => 'Bearer '.$this->config['api-key'],
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->config['url'], [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemRole],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => $this->config['max-tokens'],
        ]);

        if (! $response->successful()) {
            throw new Exception('OpenAI API error: '.$response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function promptWithImages(string $systemRole, string $userPrompt, array $images): array
    {
        $userContent = [
            ['type' => 'text', 'text' => $userPrompt],
        ];

        foreach ($images as $image) {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:'.$image['mime_type'].';base64,'.$image['base64'],
                ],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->config['api-key'],
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->config['url'], [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemRole],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => $this->config['max-tokens'],
        ]);

        if (! $response->successful()) {
            throw new Exception('OpenAI API error: '.$response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function isAvailable(): bool
    {
        $cacheKey = 'provider_health_openai';

        return Cache::remember($cacheKey, 300, function () {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$this->config['api-key'],
                ])->timeout(5)->get('https://api.openai.com/v1/models');

                if (! $response->successful()) {
                    Log::warning('OpenAI health check failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return false;
                }

                $models = $response->json('data', []);

                return count($models) > 0;
            } catch (Exception $e) {
                Log::warning('OpenAI health check exception', [
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
        return 'openai';
    }

    /**
     * {@inheritDoc}
     */
    public function getModel(): string
    {
        return $this->config['model'] ?? 'gpt-4o-mini';
    }

    /**
     * {@inheritDoc}
     */
    public function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $pricing = $this->config['pricing'] ?? [
            'input_per_1k' => 0.00015,
            'output_per_1k' => 0.0006,
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
        return $this->config['url'] ?? 'https://api.openai.com/v1/chat/completions';
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
