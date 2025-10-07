<?php

namespace App\Enums;

enum ExperienceType: string
{
    case FORMATION = 'formation';
    case EMPLOI = 'emploi';

    public function label(): string
    {
        return match ($this) {
            self::FORMATION => 'Formation',
            self::EMPLOI => 'Emploi',
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
