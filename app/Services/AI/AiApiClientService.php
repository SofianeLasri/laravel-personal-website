<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Notification;
use App\Services\ApiRequestLogger;
use App\Services\NotificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for making API calls to AI providers (OpenAI, Anthropic)
 */
class AiApiClientService
{
    public function __construct(
        private readonly ApiRequestLogger $logger,
        private readonly NotificationService $notificationService,
        private readonly AiJsonParserService $jsonParser
    ) {}

    /**
     * Call OpenAI API
     *
     * @param  array<string, mixed>  $providerConfig  The provider configuration
     * @param  string  $systemRole  The system role
     * @param  string  $prompt  The user prompt
     * @param  array<string>  $transcodedPictures  Base64 encoded pictures (optional)
     * @return array<string, mixed> The response from the AI provider
     */
    public function callOpenAi(array $providerConfig, string $systemRole, string $prompt, array $transcodedPictures = []): array
    {
        $startTime = microtime(true);
        $userContent = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];

        if (! empty($transcodedPictures)) {
            $picturesArray = array_map(fn (string $transcodedPicture) => [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:image/jpeg;base64,'.base64_encode($transcodedPicture),
                ],
            ], $transcodedPictures);
            $userContent = array_merge($userContent, $picturesArray);
        }

        $requestBody = [
            'model' => $providerConfig['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $systemRole,
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'max_tokens' => $providerConfig['max-tokens'],
            'response_format' => [
                'type' => 'json_object',
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$providerConfig['api-key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(120)
                ->post($providerConfig['url'], $requestBody);
        } catch (ConnectionException $e) {
            $responseTime = microtime(true) - $startTime;

            $this->logger->logError(
                'openai',
                $providerConfig['model'],
                $providerConfig['url'],
                $systemRole,
                $prompt,
                $e->getMessage(),
                $responseTime,
                null,
                ['pictures_count' => count($transcodedPictures)]
            );

            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'OpenAI Connection Error',
                'Failed to connect to OpenAI API: '.$e->getMessage(),
                [
                    'provider' => 'openai',
                    'model' => $providerConfig['model'],
                    'error' => $e->getMessage(),
                ]
            );

            Log::error('Failed to call OpenAI API', ['exception' => $e]);
            throw new RuntimeException('Failed to call AI provider API');
        }

        $responseTime = microtime(true) - $startTime;
        $result = $response->json();

        if (! isset($result['choices'][0]['message']['content'])) {
            $this->logger->logError(
                'openai',
                $providerConfig['model'],
                $providerConfig['url'],
                $systemRole,
                $prompt,
                'Invalid response structure',
                $responseTime,
                $response->status(),
                ['response' => $result]
            );

            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'OpenAI Response Error',
                'Invalid response structure from OpenAI API',
                [
                    'provider' => 'openai',
                    'model' => $providerConfig['model'],
                    'status' => $response->status(),
                ]
            );

            Log::error('Failed to get response from OpenAI', ['response' => $result]);
            throw new RuntimeException('Failed to get response from AI provider');
        }

        $decodedContent = $this->jsonParser->parse($result['choices'][0]['message']['content']);

        if (! is_array($decodedContent)) {
            Log::error('OpenAI returned invalid JSON content', [
                'content' => $result['choices'][0]['message']['content'],
            ]);
            throw new RuntimeException('AI provider returned invalid JSON content');
        }

        $this->logger->logSuccess(
            'openai',
            $providerConfig['model'],
            $providerConfig['url'],
            $systemRole,
            $prompt,
            $result,
            $responseTime,
            $response->status(),
            false,
            ['pictures_count' => count($transcodedPictures)]
        );

        return $decodedContent;
    }

    /**
     * Call Anthropic API
     *
     * @param  array<string, mixed>  $providerConfig  The provider configuration
     * @param  string  $systemRole  The system role
     * @param  string  $prompt  The user prompt
     * @param  array<string>  $transcodedPictures  Base64 encoded pictures (optional)
     * @return array<string, mixed> The response from the AI provider
     */
    public function callAnthropic(array $providerConfig, string $systemRole, string $prompt, array $transcodedPictures = []): array
    {
        $startTime = microtime(true);
        $userContent = [
            [
                'type' => 'text',
                'text' => $prompt,
            ],
        ];

        if (! empty($transcodedPictures)) {
            $picturesArray = array_map(fn (string $transcodedPicture) => [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'image/jpeg',
                    'data' => base64_encode($transcodedPicture),
                ],
            ], $transcodedPictures);
            $userContent = array_merge($userContent, $picturesArray);
        }

        $requestBody = [
            'model' => $providerConfig['model'],
            'max_tokens' => $providerConfig['max-tokens'],
            'system' => $systemRole,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $providerConfig['api-key'],
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(120)
                ->post($providerConfig['url'], $requestBody);
        } catch (ConnectionException $e) {
            $responseTime = microtime(true) - $startTime;

            $this->logger->logError(
                'anthropic',
                $providerConfig['model'],
                $providerConfig['url'],
                $systemRole,
                $prompt,
                $e->getMessage(),
                $responseTime,
                null,
                ['pictures_count' => count($transcodedPictures)]
            );

            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'Anthropic Connection Error',
                'Failed to connect to Anthropic API: '.$e->getMessage(),
                [
                    'provider' => 'anthropic',
                    'model' => $providerConfig['model'],
                    'error' => $e->getMessage(),
                ]
            );

            Log::error('Failed to call Anthropic API', ['exception' => $e]);
            throw new RuntimeException('Failed to call AI provider API');
        }

        $responseTime = microtime(true) - $startTime;
        $result = $response->json();

        if (! isset($result['content'][0]['text'])) {
            $this->logger->logError(
                'anthropic',
                $providerConfig['model'],
                $providerConfig['url'],
                $systemRole,
                $prompt,
                'Invalid response structure',
                $responseTime,
                $response->status(),
                ['response' => $result]
            );

            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'Anthropic Response Error',
                'Invalid response structure from Anthropic API',
                [
                    'provider' => 'anthropic',
                    'model' => $providerConfig['model'],
                    'status' => $response->status(),
                ]
            );

            Log::error('Failed to get response from Anthropic', ['response' => $result]);
            throw new RuntimeException('Failed to get response from AI provider');
        }

        $rawContent = $result['content'][0]['text'];

        // Debug: save raw content for analysis in non-production environments
        if (app()->environment('testing', 'local')) {
            file_put_contents(storage_path('logs/anthropic_raw_response.txt'), $rawContent);
            Log::info('Anthropic raw response saved to logs/anthropic_raw_response.txt', [
                'length' => strlen($rawContent),
                'first_500_chars' => substr($rawContent, 0, 500),
            ]);
        }

        $decodedContent = $this->jsonParser->parse($rawContent);

        if (! is_array($decodedContent)) {
            Log::error('Anthropic returned invalid JSON content', [
                'content' => $rawContent,
                'content_length' => strlen($rawContent),
            ]);
            throw new RuntimeException('AI provider returned invalid JSON content');
        }

        $this->logger->logSuccess(
            'anthropic',
            $providerConfig['model'],
            $providerConfig['url'],
            $systemRole,
            $prompt,
            $result,
            $responseTime,
            $response->status(),
            false,
            ['pictures_count' => count($transcodedPictures)]
        );

        return $decodedContent;
    }
}
