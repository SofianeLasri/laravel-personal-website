<?php

namespace App\Services;

use App\Models\OptimizedPicture;
use App\Models\Picture;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AiProviderService
{
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

            if (! $transcodedPicture) {
                Log::error('Failed to transcode picture', [
                    'picture' => $picture,
                ]);
                throw new RuntimeException('Failed to transcode picture');
            }

            $transcodedPictures[] = $transcodedPicture;
        }

        $selectedProvider = config('ai-provider.selected-provider');
        $providerConfig = config('ai-provider.providers.'.$selectedProvider);

        if ($selectedProvider === 'anthropic') {
            return $this->callAnthropicApi($providerConfig, $systemRole, $prompt, $transcodedPictures);
        }

        return $this->callOpenAiApi($providerConfig, $systemRole, $prompt, $transcodedPictures);
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

        if ($selectedProvider === 'anthropic') {
            return $this->callAnthropicApi($providerConfig, $systemRole, $prompt);
        }

        return $this->callOpenAiApi($providerConfig, $systemRole, $prompt);
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
            ])->post($providerConfig['url'], $requestBody);
        } catch (ConnectionException $e) {
            Log::error('Failed to call OpenAI API', [
                'exception' => $e,
            ]);
            throw new RuntimeException('Failed to call AI provider API');
        }

        $result = $response->json();

        if (! isset($result['choices'][0]['message']['content'])) {
            Log::error('Failed to get response from OpenAI', [
                'response' => $result,
            ]);
            throw new RuntimeException('Failed to get response from AI provider');
        }

        $decodedContent = json_decode($result['choices'][0]['message']['content'], true);

        if (! is_array($decodedContent)) {
            Log::error('OpenAI returned invalid JSON content', [
                'content' => $result['choices'][0]['message']['content'],
            ]);
            throw new RuntimeException('AI provider returned invalid JSON content');
        }

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
            ])->post($providerConfig['url'], $requestBody);
        } catch (ConnectionException $e) {
            Log::error('Failed to call Anthropic API', [
                'exception' => $e,
            ]);
            throw new RuntimeException('Failed to call AI provider API');
        }

        $result = $response->json();

        if (! isset($result['content'][0]['text'])) {
            Log::error('Failed to get response from Anthropic', [
                'response' => $result,
            ]);
            throw new RuntimeException('Failed to get response from AI provider');
        }

        $decodedContent = json_decode($result['content'][0]['text'], true);

        if (! is_array($decodedContent)) {
            Log::error('Anthropic returned invalid JSON content', [
                'content' => $result['content'][0]['text'],
            ]);
            throw new RuntimeException('AI provider returned invalid JSON content');
        }

        return $decodedContent;
    }
}
