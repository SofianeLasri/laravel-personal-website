<?php

namespace Database\Factories;

use App\Models\GameReview;
use App\Models\GameReviewLink;
use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameReviewLinkFactory extends Factory
{
    protected $model = GameReviewLink::class;

    public function definition(): array
    {
        return [
            'game_review_id' => GameReview::factory(),
            'type' => $this->faker->randomElement([
                'steam', 'epic', 'gog', 'playstation', 'xbox',
                'nintendo', 'mobile', 'official', 'trailer',
            ]),
            'url' => $this->faker->url(),
            'label_translation_key_id' => TranslationKey::factory()->withTranslations(),
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function steam(): static
    {
        return $this->state([
            'type' => 'steam',
            'url' => 'https://store.steampowered.com/app/'.$this->faker->numberBetween(100000, 999999),
        ]);
    }

    public function epic(): static
    {
        return $this->state([
            'type' => 'epic',
            'url' => 'https://store.epicgames.com/en-US/p/'.$this->faker->slug(),
        ]);
    }

    public function official(): static
    {
        return $this->state([
            'type' => 'official',
        ]);
    }

    public function forGameReview(GameReview $gameReview): static
    {
        return $this->state([
            'game_review_id' => $gameReview->id,
        ]);
    }
}
