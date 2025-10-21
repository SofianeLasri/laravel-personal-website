<?php

namespace App\Enums;

enum GameReviewRating: string
{
    case POSITIVE = 'positive';
    case NEGATIVE = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::POSITIVE => 'Review positive',
            self::NEGATIVE => 'Review négative',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::POSITIVE => 'Positive review',
            self::NEGATIVE => 'Negative review',
        };
    }

    /**
     * Get all available values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $rating) => $rating->value, self::cases());
    }

    /**
     * Get all labels as array
     *
     * @return array<string>
     */
    public static function labels(): array
    {
        return array_map(fn (self $rating) => $rating->label(), self::cases());
    }
}
