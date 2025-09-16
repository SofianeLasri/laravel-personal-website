<?php

namespace App\Enums;

enum BlogPostType: string
{
    case ARTICLE = 'article';
    case GAME_REVIEW = 'game_review';

    public function label(): string
    {
        return match ($this) {
            self::ARTICLE => 'Article',
            self::GAME_REVIEW => 'Critique de jeu',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ARTICLE => 'newspaper',
            self::GAME_REVIEW => 'gamepad',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
