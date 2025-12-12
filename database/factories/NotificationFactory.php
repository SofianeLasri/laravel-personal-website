<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            Notification::TYPE_SUCCESS,
            Notification::TYPE_ERROR,
            Notification::TYPE_WARNING,
            Notification::TYPE_INFO,
        ];

        $sources = [
            Notification::SOURCE_AI_PROVIDER,
            Notification::SOURCE_SYSTEM,
            Notification::SOURCE_USER,
        ];

        $hasAction = $this->faker->boolean(30); // 30% chance of having an action

        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement($types),
            'title' => $this->faker->sentence(4),
            'message' => $this->faker->paragraph(2),
            'data' => $this->faker->boolean() ? [
                'key' => $this->faker->word(),
                'value' => $this->faker->sentence(),
                'timestamp' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            ] : null,
            'source' => $this->faker->randomElement($sources),
            'action_url' => $hasAction ? $this->faker->url() : null,
            'action_label' => $hasAction ? $this->faker->words(2, true) : null,
            'is_read' => $this->faker->boolean(40), // 40% chance of being read
            'is_persistent' => $this->faker->boolean(10), // 10% chance of being persistent
            'read_at' => function (array $attributes) {
                return $attributes['is_read'] ? $this->faker->dateTimeBetween('-7 days') : null;
            },
            'expires_at' => $this->faker->boolean(20) ? $this->faker->dateTimeBetween('now', '+30 days') : null,
        ];
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): Factory
    {
        return $this->state(function () {
            return [
                'is_read' => false,
                'read_at' => null,
            ];
        });
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): Factory
    {
        return $this->state(function () {
            return [
                'is_read' => true,
                'read_at' => $this->faker->dateTimeBetween('-7 days'),
            ];
        });
    }

    /**
     * Indicate that the notification is persistent.
     */
    public function persistent(): Factory
    {
        return $this->state(function () {
            return [
                'is_persistent' => true,
            ];
        });
    }

    /**
     * Indicate that the notification is from AI provider.
     */
    public function fromAiProvider(): Factory
    {
        return $this->state(function () {
            return [
                'source' => Notification::SOURCE_AI_PROVIDER,
                'title' => 'AI Provider: '.$this->faker->sentence(3),
                'data' => [
                    'provider' => $this->faker->randomElement(['openai', 'anthropic']),
                    'model' => $this->faker->randomElement(['gpt-4', 'gpt-3.5-turbo', 'claude-3-opus', 'claude-3-sonnet']),
                    'tokens_used' => $this->faker->numberBetween(100, 5000),
                ],
            ];
        });
    }

    /**
     * Indicate that the notification has an action.
     */
    public function withAction(): Factory
    {
        return $this->state(function () {
            return [
                'action_url' => $this->faker->url(),
                'action_label' => $this->faker->words(2, true),
            ];
        });
    }

    /**
     * Indicate that the notification expires soon.
     */
    public function expiring(): Factory
    {
        return $this->state(function () {
            return [
                'expires_at' => $this->faker->dateTimeBetween('now', '+1 hour'),
            ];
        });
    }

    /**
     * Indicate that the notification is expired.
     */
    public function expired(): Factory
    {
        return $this->state(function () {
            return [
                'expires_at' => $this->faker->dateTimeBetween('-7 days', '-1 hour'),
            ];
        });
    }

    /**
     * Create a success notification.
     */
    public function success(): Factory
    {
        return $this->state(function () {
            return [
                'type' => Notification::TYPE_SUCCESS,
                'title' => 'Success: '.$this->faker->sentence(3),
            ];
        });
    }

    /**
     * Create an error notification.
     */
    public function error(): Factory
    {
        return $this->state(function () {
            return [
                'type' => Notification::TYPE_ERROR,
                'title' => 'Error: '.$this->faker->sentence(3),
            ];
        });
    }

    /**
     * Create a warning notification.
     */
    public function warning(): Factory
    {
        return $this->state(function () {
            return [
                'type' => Notification::TYPE_WARNING,
                'title' => 'Warning: '.$this->faker->sentence(3),
            ];
        });
    }

    /**
     * Create an info notification.
     */
    public function info(): Factory
    {
        return $this->state(function () {
            return [
                'type' => Notification::TYPE_INFO,
                'title' => 'Info: '.$this->faker->sentence(3),
            ];
        });
    }
}
