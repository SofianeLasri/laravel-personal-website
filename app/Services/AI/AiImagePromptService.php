<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\ImageTranscodingException;
use App\Models\Notification;
use App\Models\OptimizedPicture;
use App\Models\Picture;
use App\Services\ImageTranscodingService;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Service for handling AI prompts with image content
 */
class AiImagePromptService
{
    public function __construct(
        private readonly AiApiClientService $apiClient,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Prompt the AI provider with text and pictures
     *
     * @param  string  $systemRole  The system role to send to the AI provider
     * @param  string  $prompt  The prompt to send to the AI provider
     * @param  Picture  ...$pictures  The pictures to send to the AI provider
     * @return array<string, mixed> The response from the AI provider
     *
     * @throws ImageTranscodingException
     */
    public function prompt(string $systemRole, string $prompt, Picture ...$pictures): array
    {
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

                try {
                    $transcodedPicture = $transcodingService->transcode($picturePath, OptimizedPicture::MEDIUM_SIZE, 'jpeg');
                } catch (ImageTranscodingException $e) {
                    Log::error('Failed to transcode picture', [
                        'picture' => $picture,
                        'error' => $e->getMessage(),
                    ]);
                    throw new RuntimeException('Failed to transcode picture');
                }

                $transcodedPictures[] = $transcodedPicture;
            }

            $selectedProvider = config('ai-provider.selected-provider');
            $providerConfig = config('ai-provider.providers.'.$selectedProvider);

            if ($selectedProvider === 'anthropic') {
                return $this->apiClient->callAnthropic($providerConfig, $systemRole, $prompt, $transcodedPictures);
            }

            return $this->apiClient->callOpenAi($providerConfig, $systemRole, $prompt, $transcodedPictures);
        } catch (Exception $e) {
            $this->notificationService->createAiProviderNotification(
                Notification::TYPE_ERROR,
                'AI Provider Error',
                'Failed to process image request: '.$e->getMessage(),
                [
                    'provider' => config('ai-provider.selected-provider'),
                    'error' => $e->getMessage(),
                    'pictures_count' => count($pictures),
                ]
            );

            throw $e;
        }
    }
}
