<?php

namespace App\Services;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use RuntimeException;
use App\Models\Notification;

class AiProviderService
{
    /**
     * @var AiTranslationCacheService
     */
    private AiTranslationCacheService $cacheService;

    /**
     * @var ApiRequestLogger
     */
    private ApiRequestLogger $logger;

    /**
     * @var NotificationService
     */
    private NotificationService $notificationService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cacheService = app(AiTranslationCacheService::class);
        $this->logger = app(ApiRequestLogger::class);
        $this->notificationService = app(NotificationService::class);
    }

    /**
     * Prompt the AI provider with a text and pictures
     *
     * @param  string  $systemRole  The system role to send to the AI provider. E.g. "You are a helpful assistant."
     * @param  string  $prompt  The prompt to send to the AI provider
     * @param  Picture  ...$pictures  The pictures to send to the AI provider
     * @return array<string, mixed> The response from the AI provider.
     */
    public function promptWithPictures(string $systemRole, string $prompt, Picture ...$pictures): array
    {
        // Note: Currently not caching image-based prompts due to complexity
        // This could be added in the future by including image hashes in the cache key
        try {
            $transcodingService = app(ImageTranscodingService::class);

            $transcodedPictures = [];
            foreach ($pictures as $picture) {
                if ($picture->path_original === null) {
                    Log::error('Picture has no original path', [
                        'picture' => $picture,
                    ]);
                    throw new RuntimeException('Picture has no original path');
                }

                $picturePath = Storage::disk('public')->get($picture->path_original);

                if ($picturePath === null) {
                    Log::error('Failed to get picture content from storage', [
                        'picture' => $picture,
                        'path' => $picture->path_original,
                    ]);
                    throw new RuntimeException('Failed to get picture content from storage');
                }

                $transcodedPicture = $transcodingService->transcode($picturePath, OptimizedPicture::MEDIUM_SIZE, 'jpeg');

                if (!$transcodedPicture) {
                    Log::error('Failed to transcode picture', [
                        'picture' => $picture,
                    ]);
                    throw new RuntimeException('Failed to transcode picture');
                }

                $transcodedPictures[] = $transcodedPicture;
            }

            $selectedProvider = config('ai-provider.selected-provider');
            $providerConfig = config('ai-provider.providers.' . $selectedProvider);

            if ($selectedProvider === 'anthropic') {
                return $this->callAnthropicApi($providerConfig, $systemRole, $prompt, $transcodedPictures);
            }

            return $this->callOpenAiApi($providerConfig, $systemRole, $prompt, $transcodedPictures);
        } catch (Exception $e) {
            // Send error notification
            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'AI Provider Error',
                'Failed to process image request: ' . $e->getMessage(),
                [
                    'provider' => config('ai-provider.selected-provider'),
                    'error' => $e->getMessage(),
                    'pictures_count' => count($pictures),
                ]
            );

            throw $e;
        }
    }

    /**
     * Prompt the AI provider with text only
     *
     * @param  string  $systemRole  The system role to send to the AI provider
     * @param  string  $prompt  The prompt to send to the AI provider
     * @return array<string, mixed> The response from the AI provider
     */
    public function prompt(string $systemRole, string $prompt): array
    {
        $selectedProvider = config('ai-provider.selected-provider');
        $providerConfig = config('ai-provider.providers.'.$selectedProvider);

        // Check cache if enabled
        if ($this->cacheService->isEnabled()) {
            $cacheKey = $this->cacheService->generateCacheKey($selectedProvider, $systemRole, $prompt);
            $cached = $this->cacheService->get($cacheKey, $this->cacheService->getTtl());

            if ($cached !== null) {
                Log::info('Using cached AI response', [
                    'provider' => $selectedProvider,
                    'cache_key' => $cacheKey,
                ]);

                // Log cached response
                $this->logger->logSuccess(
                    $selectedProvider,
                    $providerConfig['model'],
                    $providerConfig['url'],
                    $systemRole,
                    $prompt,
                    $cached,
                    0.001, // Minimal response time for cache hit
                    200,
                    true, // Mark as cached
                    null
                );
                
                return $cached;
            }
        }

        // Call the API
        if ($selectedProvider === 'anthropic') {
            $response = $this->callAnthropicApi($providerConfig, $systemRole, $prompt);
        } else {
            $response = $this->callOpenAiApi($providerConfig, $systemRole, $prompt);
        }

        // Store in cache if enabled
        if ($this->cacheService->isEnabled()) {
            $this->cacheService->put($selectedProvider, $systemRole, $prompt, $response);
        }

        return $response;
    }

    /**
     * Call OpenAI API
     *
     * @param  array<string, mixed>  $providerConfig  The provider configuration
     * @param  string  $systemRole  The system role
     * @param  string  $prompt  The user prompt
     * @param  array<string>  $transcodedPictures  Base64 encoded pictures (optional)
     * @return array<string, mixed> The response from the AI provider
     */
    private function callOpenAiApi(array $providerConfig, string $systemRole, string $prompt, array $transcodedPictures = []): array
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

            // Log the error
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

            // Send error notification
            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'OpenAI Connection Error',
                'Failed to connect to OpenAI API: ' . $e->getMessage(),
                [
                    'provider' => 'openai',
                    'model' => $providerConfig['model'],
                    'error' => $e->getMessage(),
                ]
            );
            
            Log::error('Failed to call OpenAI API', [
                'exception' => $e,
            ]);
            throw new RuntimeException('Failed to call AI provider API');
        }

        $responseTime = microtime(true) - $startTime;
        $result = $response->json();

        if (! isset($result['choices'][0]['message']['content'])) {
            // Log the error
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

            // Send error notification
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
            
            Log::error('Failed to get response from OpenAI', [
                'response' => $result,
            ]);
            throw new RuntimeException('Failed to get response from AI provider');
        }

        // Use JSON Machine for robust parsing
        $decodedContent = $this->parseJsonWithJsonMachine($result['choices'][0]['message']['content']);

        if (! is_array($decodedContent)) {
            Log::error('OpenAI returned invalid JSON content', [
                'content' => $result['choices'][0]['message']['content'],
            ]);
            throw new RuntimeException('AI provider returned invalid JSON content');
        }

        // Log successful request
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
    private function callAnthropicApi(array $providerConfig, string $systemRole, string $prompt, array $transcodedPictures = []): array
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
                ->timeout(120) // Increase timeout for long responses
                ->post($providerConfig['url'], $requestBody);
        } catch (ConnectionException $e) {
            $responseTime = microtime(true) - $startTime;

            // Log the error
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

            // Send error notification
            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'Anthropic Connection Error',
                'Failed to connect to Anthropic API: ' . $e->getMessage(),
                [
                    'provider' => 'anthropic',
                    'model' => $providerConfig['model'],
                    'error' => $e->getMessage(),
                ]
            );
            
            Log::error('Failed to call Anthropic API', [
                'exception' => $e,
            ]);
            throw new RuntimeException('Failed to call AI provider API');
        }

        $responseTime = microtime(true) - $startTime;
        $result = $response->json();

        if (! isset($result['content'][0]['text'])) {
            // Log the error
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

            // Send error notification
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
            
            Log::error('Failed to get response from Anthropic', [
                'response' => $result,
            ]);
            throw new RuntimeException('Failed to get response from AI provider');
        }

        // Use JSON Machine for robust parsing
        $rawContent = $result['content'][0]['text'];

        // Debug: save raw content for analysis
        if (app()->environment('testing', 'local')) {
            file_put_contents(storage_path('logs/anthropic_raw_response.txt'), $rawContent);
            Log::info('Anthropic raw response saved to logs/anthropic_raw_response.txt', [
                'length' => strlen($rawContent),
                'first_500_chars' => substr($rawContent, 0, 500),
            ]);
        }

        $decodedContent = $this->parseJsonWithJsonMachine($rawContent);

        if (! is_array($decodedContent)) {
            Log::error('Anthropic returned invalid JSON content', [
                'content' => $rawContent,
                'content_length' => strlen($rawContent),
            ]);
            throw new RuntimeException('AI provider returned invalid JSON content');
        }

        // Log successful request
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

    /**
     * Parse JSON using JSON Machine for robust handling of incomplete or malformed JSON
     *
     * @param  string  $jsonString  The potentially incomplete JSON string
     * @return array<string, mixed>|null The parsed JSON array or null if parsing fails
     */
    private function parseJsonWithJsonMachine(string $jsonString): ?array
    {
        // First, try standard JSON decode for performance
        $decoded = json_decode($jsonString, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Log JSON error for debugging
        $jsonError = json_last_error_msg();
        Log::info('Standard JSON decode failed', [
            'error' => $jsonError,
            'first_100_chars' => substr($jsonString, 0, 100),
        ]);

        // Check if this is a JSON with unescaped newlines (common with AI responses)
        // Try to fix it by extracting and properly escaping the message content
        if (preg_match('/^\s*\{\s*"message"\s*:\s*"(.*)"\s*}\s*$/s', $jsonString, $matches)) {
            $messageContent = $matches[1];

            // Properly escape the message content for JSON
            $escapedMessage = json_encode($messageContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Rebuild the JSON with properly escaped content
            $fixedJson = '{"message":'.$escapedMessage.'}';

            $decoded = json_decode($fixedJson, true);
            if (is_array($decoded)) {
                Log::info('JSON parsed successfully after fixing unescaped newlines');

                return $decoded;
            }
        }

        // Try to parse with JSON Machine which handles incomplete JSON better
        try {
            // Clean the JSON string
            $cleanedJson = trim($jsonString);

            // Try to fix incomplete JSON by ensuring proper closure
            $openBraces = substr_count($cleanedJson, '{');
            $closeBraces = substr_count($cleanedJson, '}');
            $openBrackets = substr_count($cleanedJson, '[');
            $closeBrackets = substr_count($cleanedJson, ']');

            // Add missing closing braces/brackets
            $cleanedJson .= str_repeat(']', max(0, $openBrackets - $closeBrackets));
            $cleanedJson .= str_repeat('}', max(0, $openBraces - $closeBraces));

            // Try standard decode again after cleanup
            $decoded = json_decode($cleanedJson, true);
            if (is_array($decoded)) {
                Log::info('JSON parsed successfully after bracket completion', [
                    'added_brackets' => max(0, $openBrackets - $closeBrackets),
                    'added_braces' => max(0, $openBraces - $closeBraces),
                ]);

                return $decoded;
            }

            // Use JSON Machine with ExtJsonDecoder for better error handling
            $items = Items::fromString($cleanedJson, [
                'decoder' => new ExtJsonDecoder(true),
            ]);

            // Convert the iterator to an array
            $result = [];
            foreach ($items as $key => $value) {
                $result[$key] = $value;
            }

            if (! empty($result)) {
                Log::info('JSON parsed successfully with JSON Machine', [
                    'original_length' => strlen($jsonString),
                    'cleaned_length' => strlen($cleanedJson),
                ]);

                return $result;
            }
        } catch (Exception $e) {
            Log::warning('JSON Machine parsing failed, attempting fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Enhanced fallback: try to extract the message field with multiline support
        // This regex handles multiline content in the message field
        if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.|\\\\n|\\\\r)*)"/s', $jsonString, $matches)) {
            $escapedMessage = $matches[1];
            // Decode the JSON-escaped string
            $message = json_decode('"'.$escapedMessage.'"');
            if ($message !== null) {
                Log::warning('JSON recovered by extracting message field', [
                    'message_length' => strlen($message),
                ]);

                return ['message' => $message];
            }
        }

        return null;
    }
}
