<?php

namespace Database\Factories;

use App\Models\ApiRequestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ApiRequestLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => $this->faker->randomElement(['openai', 'anthropic', 'google']),
            'model' => $this->faker->randomElement(['gpt-4', 'gpt-3.5-turbo', 'claude-3-opus', 'claude-3-sonnet']),
            'endpoint' => $this->faker->randomElement(['chat/completions', 'messages']),
            'status' => $this->faker->randomElement(['success', 'error', 'timeout', 'fallback']),
            'http_status_code' => $this->faker->randomElement([200, 400, 401, 429, 500, 503]),
            'error_message' => $this->faker->optional(0.3)->sentence(),
            'system_prompt' => $this->faker->paragraph(),
            'user_prompt' => $this->faker->paragraph(),
            'response' => [
                'content' => $this->faker->sentence(),
                'role' => 'assistant',
            ],
            'prompt_tokens' => $this->faker->numberBetween(10, 1000),
            'completion_tokens' => $this->faker->numberBetween(10, 500),
            'total_tokens' => function (array $attributes) {
                return ($attributes['prompt_tokens'] ?? 0) + ($attributes['completion_tokens'] ?? 0);
            },
            'response_time' => $this->faker->randomFloat(3, 0.1, 10.0),
            'estimated_cost' => $this->faker->randomFloat(6, 0.0001, 0.01),
            'fallback_provider' => $this->faker->optional(0.1)->randomElement(['openai', 'anthropic']),
            'metadata' => [
                'temperature' => $this->faker->randomFloat(2, 0, 1),
                'max_tokens' => $this->faker->numberBetween(100, 2000),
            ],
            'cached' => $this->faker->boolean(30), // 30% chance of being cached
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => function (array $attributes) {
                return $attributes['created_at'];
            },
        ];
    }

    /**
     * Indicate that the request was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'http_status_code' => 200,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the request failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'http_status_code' => $this->faker->randomElement([400, 401, 429, 500, 503]),
            'error_message' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the request was cached.
     */
    public function cached(): static
    {
        return $this->state(fn (array $attributes) => [
            'cached' => true,
            'response_time' => $this->faker->randomFloat(3, 0.01, 0.1), // Faster response for cached
        ]);
    }

    /**
     * Indicate that the request was not cached.
     */
    public function notCached(): static
    {
        return $this->state(fn (array $attributes) => [
            'cached' => false,
            'response_time' => $this->faker->randomFloat(3, 0.5, 5.0), // Slower response for non-cached
        ]);
    }

    /**
     * Set a specific provider.
     */
    public function provider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
        ]);
    }

    /**
     * Set a specific status.
     */
    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
