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
     * @return array The response from the AI provider.
     */
    public function promptWithPictures(string $systemRole, string $prompt, Picture ...$pictures): array
    {
        $transcodingService = app(ImageTranscodingService::class);

        $transcodedPictures = [];
        foreach ($pictures as $picture) {
            $picturePath = Storage::disk('public')->get($picture->path_original);
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

        $picturesArray = array_map(fn (string $transcodedPicture) => [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/jpeg;base64,'.base64_encode($transcodedPicture),
            ],
        ], $transcodedPictures);

        $requestBody = [
            'headers' => [
                'Authorization' => 'Bearer '.config('ai-provider.providers.'.$selectedProvider.'.api-key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'model' => config('ai-provider.providers.'.$selectedProvider.'.model'),
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
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            ...$picturesArray,
                        ],
                    ],
                ],
                'max_tokens' => config('ai-provider.providers.'.$selectedProvider.'.max-tokens'),
                'response_format' => [
                    'type' => 'json_object',
                ],
            ],
        ];

        return $this->callApi(config('ai-provider.providers.'.$selectedProvider.'.url'), $requestBody);
    }

    public function prompt(string $systemRole, string $prompt): array
    {
        $selectedProvider = config('ai-provider.selected-provider');

        $requestBody = ['model' => config('ai-provider.providers.'.$selectedProvider.'.model'),
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
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'max_tokens' => config('ai-provider.providers.'.$selectedProvider.'.max-tokens'),
            'response_format' => [
                'type' => 'json_object',
            ],
        ];

        return $this->callApi(config('ai-provider.providers.'.$selectedProvider.'.url'), $requestBody);
    }

    /**
     * Call the AI provider API
     *
     * @param  string  $url  The URL of the AI provider API
     * @param  array  $requestBody  The request body to send to the AI provider API
     * @return array The response from the AI provider
     */
    private function callApi(string $url, array $requestBody): array
    {
        $selectedProvider = config('ai-provider.selected-provider');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('ai-provider.providers.'.$selectedProvider.'.api-key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($url, $requestBody);
        } catch (ConnectionException $e) {
            Log::error('Failed to call AI provider API', [
                'exception' => $e,
            ]);
            throw new RuntimeException('Failed to call AI provider API');
        }
        $result = $response->json();

        if (! isset($result['choices'][0]['message']['content'])) {
            Log::error('Failed to get response from AI provider', [
                'response' => $result,
            ]);
            throw new RuntimeException('Failed to get response from AI provider');
        }

        return json_decode($result['choices'][0]['message']['content'], true);
    }
}
