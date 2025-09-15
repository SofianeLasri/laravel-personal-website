<?php

namespace App\Enums;

enum BlogPostType: string
{
    case ARTICLE = 'article';
    case TUTORIAL = 'tutorial';
    case NEWS = 'news';
    case REVIEW = 'review';
    case GUIDE = 'guide';
    case GAME_REVIEW = 'game_review';

    public function label(): string
    {
        return match ($this) {
            self::ARTICLE => 'Article',
            self::TUTORIAL => 'Tutoriel',
            self::NEWS => 'Actualité',
            self::REVIEW => 'Critique',
            self::GUIDE => 'Guide',
            self::GAME_REVIEW => 'Critique de jeu',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ARTICLE => 'newspaper',
            self::TUTORIAL => 'graduation-cap',
            self::NEWS => 'bullhorn',
            self::REVIEW => 'star',
            self::GUIDE => 'book',
            self::GAME_REVIEW => 'gamepad',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
