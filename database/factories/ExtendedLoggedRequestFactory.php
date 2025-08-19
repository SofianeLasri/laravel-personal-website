<?php

namespace Database\Factories;

use App\Models\ExtendedLoggedRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use SlProjects\LaravelRequestLogger\app\Models\IpAddress;
use SlProjects\LaravelRequestLogger\app\Models\MimeType;
use SlProjects\LaravelRequestLogger\app\Models\Url;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;
use SlProjects\LaravelRequestLogger\Enums\HttpMethod;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExtendedLoggedRequest>
 */
class ExtendedLoggedRequestFactory extends Factory
{
    protected $model = ExtendedLoggedRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ip_address_id' => IpAddress::factory(),
            'country_code' => $this->faker->countryCode(),
            'method' => $this->faker->randomElement(HttpMethod::cases()),
            'content_length' => $this->faker->optional()->numberBetween(0, 10000),
            'status_code' => $this->faker->randomElement([200, 201, 204, 301, 302, 400, 401, 403, 404, 500]),
            'user_agent_id' => UserAgent::factory(),
            'mime_type_id' => MimeType::factory(),
            'url_id' => Url::factory(),
            'referer_url_id' => $this->faker->optional()->randomElement([null, Url::factory()]),
            'origin_url_id' => $this->faker->optional()->randomElement([null, Url::factory()]),
            'user_id' => null,
            'is_bot_by_frequency' => false,
            'is_bot_by_user_agent' => false,
            'is_bot_by_parameters' => false,
            'bot_detection_metadata' => null,
            'bot_analyzed_at' => null,
        ];
    }
}
