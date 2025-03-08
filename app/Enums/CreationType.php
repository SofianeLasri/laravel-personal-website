<?php

namespace App\Enums;

enum CreationType: string
{
    case PORTFOLIO = 'portfolio';
    case GAME = 'game';
    case LIBRARY = 'library';
    case WEBSITE = 'website';
    case TOOL = 'tool';
    case MAP = 'map';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PORTFOLIO => 'Portfolio',
            self::GAME => 'Jeu',
            self::LIBRARY => 'Bibliothèque',
            self::WEBSITE => 'Site internet',
            self::TOOL => 'Outil',
            self::MAP => 'Carte',
            self::OTHER => 'Autre',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
