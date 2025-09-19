<?php

namespace Tests\Unit;

use App\Enums\CategoryColor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryColor::class)]
class CategoryColorTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('red', CategoryColor::RED->value);
        $this->assertSame('blue', CategoryColor::BLUE->value);
        $this->assertSame('green', CategoryColor::GREEN->value);
        $this->assertSame('yellow', CategoryColor::YELLOW->value);
        $this->assertSame('purple', CategoryColor::PURPLE->value);
        $this->assertSame('pink', CategoryColor::PINK->value);
        $this->assertSame('orange', CategoryColor::ORANGE->value);
        $this->assertSame('gray', CategoryColor::GRAY->value);
    }

    #[Test]
    #[DataProvider('labelProvider')]
    public function it_returns_correct_french_labels(CategoryColor $color, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $color->label());
    }

    #[Test]
    #[DataProvider('labelEnProvider')]
    public function it_returns_correct_english_labels(CategoryColor $color, string $expectedLabel): void
    {
        $this->assertSame($expectedLabel, $color->labelEn());
    }

    #[Test]
    #[DataProvider('badgeClassProvider')]
    public function it_returns_correct_badge_classes(CategoryColor $color, string $expectedClass): void
    {
        $this->assertSame($expectedClass, $color->badgeClass());
    }

    #[Test]
    #[DataProvider('hexColorProvider')]
    public function it_returns_correct_hex_colors(CategoryColor $color, string $expectedHex): void
    {
        $this->assertSame($expectedHex, $color->hexColor());
    }

    #[Test]
    public function it_returns_all_values(): void
    {
        $values = CategoryColor::values();

        $this->assertIsArray($values);
        $this->assertCount(8, $values);
        $this->assertContains('red', $values);
        $this->assertContains('blue', $values);
        $this->assertContains('green', $values);
        $this->assertContains('yellow', $values);
        $this->assertContains('purple', $values);
        $this->assertContains('pink', $values);
        $this->assertContains('orange', $values);
        $this->assertContains('gray', $values);
    }

    #[Test]
    public function it_returns_all_labels(): void
    {
        $labels = CategoryColor::labels();

        $this->assertIsArray($labels);
        $this->assertCount(8, $labels);
        $this->assertContains('Rouge', $labels);
        $this->assertContains('Bleu', $labels);
        $this->assertContains('Vert', $labels);
        $this->assertContains('Jaune', $labels);
        $this->assertContains('Violet', $labels);
        $this->assertContains('Rose', $labels);
        $this->assertContains('Orange', $labels);
        $this->assertContains('Gris', $labels);
    }

    #[Test]
    #[DataProvider('fromHexProvider')]
    public function it_returns_correct_enum_from_hex(string $hexColor, CategoryColor $expectedColor): void
    {
        $this->assertSame($expectedColor, CategoryColor::fromHex($hexColor));
    }

    #[Test]
    public function it_returns_orange_as_default_for_unknown_hex(): void
    {
        $this->assertSame(CategoryColor::ORANGE, CategoryColor::fromHex('#ffffff'));
        $this->assertSame(CategoryColor::ORANGE, CategoryColor::fromHex('#000000'));
        $this->assertSame(CategoryColor::ORANGE, CategoryColor::fromHex('#123456'));
    }

    #[Test]
    public function it_returns_all_enum_data(): void
    {
        $all = CategoryColor::all();

        $this->assertIsArray($all);
        $this->assertCount(8, $all);

        foreach ($all as $colorData) {
            $this->assertArrayHasKey('value', $colorData);
            $this->assertArrayHasKey('label', $colorData);
            $this->assertArrayHasKey('labelEn', $colorData);
            $this->assertArrayHasKey('hexColor', $colorData);
            $this->assertArrayHasKey('badgeClass', $colorData);

            $this->assertIsString($colorData['value']);
            $this->assertIsString($colorData['label']);
            $this->assertIsString($colorData['labelEn']);
            $this->assertIsString($colorData['hexColor']);
            $this->assertIsString($colorData['badgeClass']);
        }

        // Test specific case
        $redData = $all[0];
        $this->assertSame('red', $redData['value']);
        $this->assertSame('Rouge', $redData['label']);
        $this->assertSame('Red', $redData['labelEn']);
        $this->assertSame('#ef4444', $redData['hexColor']);
        $this->assertSame('bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', $redData['badgeClass']);
    }

    public static function labelProvider(): array
    {
        return [
            [CategoryColor::RED, 'Rouge'],
            [CategoryColor::BLUE, 'Bleu'],
            [CategoryColor::GREEN, 'Vert'],
            [CategoryColor::YELLOW, 'Jaune'],
            [CategoryColor::PURPLE, 'Violet'],
            [CategoryColor::PINK, 'Rose'],
            [CategoryColor::ORANGE, 'Orange'],
            [CategoryColor::GRAY, 'Gris'],
        ];
    }

    public static function labelEnProvider(): array
    {
        return [
            [CategoryColor::RED, 'Red'],
            [CategoryColor::BLUE, 'Blue'],
            [CategoryColor::GREEN, 'Green'],
            [CategoryColor::YELLOW, 'Yellow'],
            [CategoryColor::PURPLE, 'Purple'],
            [CategoryColor::PINK, 'Pink'],
            [CategoryColor::ORANGE, 'Orange'],
            [CategoryColor::GRAY, 'Gray'],
        ];
    }

    public static function badgeClassProvider(): array
    {
        return [
            [CategoryColor::RED, 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'],
            [CategoryColor::BLUE, 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'],
            [CategoryColor::GREEN, 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
            [CategoryColor::YELLOW, 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'],
            [CategoryColor::PURPLE, 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'],
            [CategoryColor::PINK, 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200'],
            [CategoryColor::ORANGE, 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'],
            [CategoryColor::GRAY, 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'],
        ];
    }

    public static function hexColorProvider(): array
    {
        return [
            [CategoryColor::RED, '#ef4444'],
            [CategoryColor::BLUE, '#3b82f6'],
            [CategoryColor::GREEN, '#22c55e'],
            [CategoryColor::YELLOW, '#eab308'],
            [CategoryColor::PURPLE, '#a855f7'],
            [CategoryColor::PINK, '#ec4899'],
            [CategoryColor::ORANGE, '#f97316'],
            [CategoryColor::GRAY, '#6b7280'],
        ];
    }

    public static function fromHexProvider(): array
    {
        return [
            // RED variants
            ['#ef4444', CategoryColor::RED],
            ['#dc2626', CategoryColor::RED],
            ['#b91c1c', CategoryColor::RED],
            ['#991b1b', CategoryColor::RED],

            // BLUE variants
            ['#3b82f6', CategoryColor::BLUE],
            ['#2563eb', CategoryColor::BLUE],
            ['#1d4ed8', CategoryColor::BLUE],
            ['#1e40af', CategoryColor::BLUE],

            // GREEN variants
            ['#22c55e', CategoryColor::GREEN],
            ['#16a34a', CategoryColor::GREEN],
            ['#15803d', CategoryColor::GREEN],
            ['#166534', CategoryColor::GREEN],

            // YELLOW variants
            ['#eab308', CategoryColor::YELLOW],
            ['#ca8a04', CategoryColor::YELLOW],
            ['#a16207', CategoryColor::YELLOW],
            ['#854d0e', CategoryColor::YELLOW],

            // PURPLE variants
            ['#a855f7', CategoryColor::PURPLE],
            ['#9333ea', CategoryColor::PURPLE],
            ['#7c3aed', CategoryColor::PURPLE],
            ['#6d28d9', CategoryColor::PURPLE],

            // PINK variants
            ['#ec4899', CategoryColor::PINK],
            ['#db2777', CategoryColor::PINK],
            ['#be185d', CategoryColor::PINK],
            ['#9d174d', CategoryColor::PINK],

            // ORANGE variants
            ['#f97316', CategoryColor::ORANGE],
            ['#ea580c', CategoryColor::ORANGE],
            ['#c2410c', CategoryColor::ORANGE],
            ['#9a3412', CategoryColor::ORANGE],

            // GRAY variants
            ['#6b7280', CategoryColor::GRAY],
            ['#4b5563', CategoryColor::GRAY],
            ['#374151', CategoryColor::GRAY],
            ['#1f2937', CategoryColor::GRAY],
        ];
    }
}