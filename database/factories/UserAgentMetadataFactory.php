<?php

namespace Database\Factories;

use App\Models\UserAgentMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;
use SlProjects\LaravelRequestLogger\app\Models\UserAgent;

class UserAgentMetadataFactory extends Factory
{
    protected $model = UserAgentMetadata::class;

    public function definition(): array
    {
        return [
            'is_bot' => $this->faker->boolean(),

            'user_agent_id' => UserAgent::factory(),
        ];
    }
}
