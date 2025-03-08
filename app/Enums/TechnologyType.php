<?php

namespace App\Enums;

enum TechnologyType: string
{
    case FRAMEWORK = 'framework';
    case LIBRARY = 'library';
    case LANGUAGE = 'language';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FRAMEWORK => 'Framework',
            self::LIBRARY => 'Bibliothèque',
            self::LANGUAGE => 'Langage',
            self::OTHER => 'Autre',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
