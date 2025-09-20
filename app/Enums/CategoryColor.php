<?php

namespace App\Enums;

enum CategoryColor: string
{
    case RED = 'red';
    case BLUE = 'blue';
    case GREEN = 'green';
    case YELLOW = 'yellow';
    case PURPLE = 'purple';
    case PINK = 'pink';
    case ORANGE = 'orange';
    case GRAY = 'gray';

    public function label(): string
    {
        return match ($this) {
            self::RED => 'Rouge',
            self::BLUE => 'Bleu',
            self::GREEN => 'Vert',
            self::YELLOW => 'Jaune',
            self::PURPLE => 'Violet',
            self::PINK => 'Rose',
            self::ORANGE => 'Orange',
            self::GRAY => 'Gris',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::RED => 'Red',
            self::BLUE => 'Blue',
            self::GREEN => 'Green',
            self::YELLOW => 'Yellow',
            self::PURPLE => 'Purple',
            self::PINK => 'Pink',
            self::ORANGE => 'Orange',
            self::GRAY => 'Gray',
        };
    }

    /**
     * Get Tailwind CSS class for badge styling
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::RED => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            self::BLUE => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            self::GREEN => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::YELLOW => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::PURPLE => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            self::PINK => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
            self::ORANGE => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            self::GRAY => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }

    /**
     * Get hex color value
     */
    public function hexColor(): string
    {
        return match ($this) {
            self::RED => '#ef4444',
            self::BLUE => '#3b82f6',
            self::GREEN => '#22c55e',
            self::YELLOW => '#eab308',
            self::PURPLE => '#a855f7',
            self::PINK => '#ec4899',
            self::ORANGE => '#f97316',
            self::GRAY => '#6b7280',
        };
    }

    /**
     * Get all available values as array
     */
    public static function values(): array
    {
        return array_map(fn (self $color) => $color->value, self::cases());
    }

    /**
     * Get all labels as array
     */
    public static function labels(): array
    {
        return array_map(fn (self $color) => $color->label(), self::cases());
    }

    /**
     * Get the closest enum value from a hex color
     */
    public static function fromHex(string $hexColor): self
    {
        $hexColor = strtolower($hexColor);

        return match ($hexColor) {
            '#ef4444', '#dc2626', '#b91c1c', '#991b1b' => self::RED,
            '#3b82f6', '#2563eb', '#1d4ed8', '#1e40af' => self::BLUE,
            '#22c55e', '#16a34a', '#15803d', '#166534' => self::GREEN,
            '#eab308', '#ca8a04', '#a16207', '#854d0e' => self::YELLOW,
            '#a855f7', '#9333ea', '#7c3aed', '#6d28d9' => self::PURPLE,
            '#ec4899', '#db2777', '#be185d', '#9d174d' => self::PINK,
            '#f97316', '#ea580c', '#c2410c', '#9a3412' => self::ORANGE,
            '#6b7280', '#4b5563', '#374151', '#1f2937' => self::GRAY,
            default => self::ORANGE, // Default fallback
        };
    }

    /**
     * Get all enum values with their hex colors as array
     */
    public static function all(): array
    {
        return array_map(fn (self $color) => [
            'value' => $color->value,
            'label' => $color->label(),
            'labelEn' => $color->labelEn(),
            'hexColor' => $color->hexColor(),
            'badgeClass' => $color->badgeClass(),
        ], self::cases());
    }
}
