<?php

namespace App\Contracts;

interface AiProviderInterface
{
    /**
     * Send a prompt to the AI provider
     *
     * @param  string  $systemRole  The system role/context for the AI
     * @param  string  $userPrompt  The user's prompt
     * @return array{content: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}}
     *
     * @throws \Exception
     */
    public function prompt(string $systemRole, string $userPrompt): array;

    /**
     * Send a prompt with images to the AI provider
     *
     * @param  string  $systemRole  The system role/context for the AI
     * @param  string  $userPrompt  The user's prompt
     * @param  array<array{base64: string, mime_type: string}>  $images  Array of images with base64 data and mime type
     * @return array{content: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}}
     *
     * @throws \Exception
     */
    public function promptWithImages(string $systemRole, string $userPrompt, array $images): array;

    /**
     * Check if the provider is available
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name
     */
    public function getName(): string;

    /**
     * Get the current model being used
     */
    public function getModel(): string;

    /**
     * Estimate the cost for a request
     *
     * @param  int  $inputTokens  Number of input tokens
     * @param  int  $outputTokens  Number of output tokens
     * @return float Cost in USD
     */
    public function estimateCost(int $inputTokens, int $outputTokens): float;

    /**
     * Get the provider's API endpoint
     */
    public function getEndpoint(): string;

    /**
     * Get the maximum tokens allowed for this provider
     */
    public function getMaxTokens(): int;

    /**
     * Get the provider configuration
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;
}
