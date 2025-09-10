<?php

namespace App\Services\AiProviders;

use App\Contracts\AiProviderInterface;
use InvalidArgumentException;

class AiProviderFactory
{
    /**
     * Create an AI provider instance
     *
     * @param string $provider The provider name
     * @return AiProviderInterface
     * @throws InvalidArgumentException
     */
    public static function create(string $provider): AiProviderInterface
    {
        $config = config("ai-provider.providers.{$provider}");

        if (!$config) {
            throw new InvalidArgumentException("Configuration not found for provider: {$provider}");
        }

        return match($provider) {
            'openai' => new OpenAiProvider($config),
            'anthropic' => new AnthropicProvider($config),
            default => throw new InvalidArgumentException("Unknown provider: {$provider}")
        };
    }

    /**
     * Get all available providers
     *
     * @return array<string>
     */
    public static function getAvailableProviders(): array
    {
        return array_keys(config('ai-provider.providers', []));
    }

    /**
     * Check if a provider is configured
     *
     * @param string $provider
     * @return bool
     */
    public static function isProviderConfigured(string $provider): bool
    {
        $config = config("ai-provider.providers.{$provider}");
        return $config && !empty($config['api-key']);
    }
}