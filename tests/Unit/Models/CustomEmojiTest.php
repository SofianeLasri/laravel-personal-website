<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\CustomEmoji;
use App\Models\Picture;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

#[CoversClass(CustomEmoji::class)]
class CustomEmojiTest extends TestCase
{
    #[Test]
    #[TestDox('It has correct fillable attributes')]
    public function it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'name',
            'picture_id',
        ];

        $emoji = new CustomEmoji;

        $this->assertEquals($fillable, $emoji->getFillable());
    }

    #[Test]
    #[TestDox('It uses correct table name')]
    public function it_uses_correct_table_name(): void
    {
        $emoji = new CustomEmoji;

        $this->assertEquals('custom_emojis', $emoji->getTable());
    }

    #[Test]
    #[TestDox('It has timestamps')]
    public function it_has_timestamps(): void
    {
        $emoji = new CustomEmoji;

        $this->assertTrue($emoji->usesTimestamps());
    }

    #[Test]
    #[TestDox('It uses HasFactory trait')]
    public function it_uses_has_factory_trait(): void
    {
        $this->assertContains(
            'Illuminate\Database\Eloquent\Factories\HasFactory',
            class_uses_recursive(CustomEmoji::class)
        );

        $factory = CustomEmoji::factory();
        $this->assertInstanceOf(\Database\Factories\CustomEmojiFactory::class, $factory);
    }

    #[Test]
    #[TestDox('It has picture relationship defined')]
    public function it_has_picture_relationship_defined(): void
    {
        $emoji = new CustomEmoji;
        $relation = $emoji->picture();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('picture_id', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getOwnerKeyName());
        $this->assertEquals(Picture::class, $relation->getRelated()::class);
    }

    #[Test]
    #[TestDox('isValidName() accepts valid alphanumeric names')]
    public function is_valid_name_accepts_valid_alphanumeric_names(): void
    {
        $this->assertTrue(CustomEmoji::isValidName('valid_name'));
        $this->assertTrue(CustomEmoji::isValidName('ValidName123'));
        $this->assertTrue(CustomEmoji::isValidName('emoji123'));
        $this->assertTrue(CustomEmoji::isValidName('UPPERCASE'));
        $this->assertTrue(CustomEmoji::isValidName('lowercase'));
        $this->assertTrue(CustomEmoji::isValidName('MixedCase_123'));
        $this->assertTrue(CustomEmoji::isValidName('name_with_underscores'));
        $this->assertTrue(CustomEmoji::isValidName('a1'));
    }

    #[Test]
    #[TestDox('isValidName() rejects names with special characters')]
    public function is_valid_name_rejects_names_with_special_characters(): void
    {
        $this->assertFalse(CustomEmoji::isValidName('invalid-name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid.name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid@name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid!name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid#name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid$name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid%name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid&name'));
        $this->assertFalse(CustomEmoji::isValidName('invalid*name'));
        $this->assertFalse(CustomEmoji::isValidName('emoji-test'));
    }

    #[Test]
    #[TestDox('isValidName() rejects names shorter than minimum length')]
    public function is_valid_name_rejects_names_shorter_than_minimum_length(): void
    {
        config(['emoji.name_min_length' => 2]);

        $this->assertFalse(CustomEmoji::isValidName(''));
        $this->assertFalse(CustomEmoji::isValidName('a'));
    }

    #[Test]
    #[TestDox('isValidName() accepts names at minimum length boundary')]
    public function is_valid_name_accepts_names_at_minimum_length_boundary(): void
    {
        config(['emoji.name_min_length' => 2]);

        $this->assertTrue(CustomEmoji::isValidName('ab'));
        $this->assertTrue(CustomEmoji::isValidName('a1'));
    }

    #[Test]
    #[TestDox('isValidName() rejects names longer than maximum length')]
    public function is_valid_name_rejects_names_longer_than_maximum_length(): void
    {
        config(['emoji.name_max_length' => 50]);

        $longName = str_repeat('a', 51);
        $this->assertFalse(CustomEmoji::isValidName($longName));

        $veryLongName = str_repeat('a', 100);
        $this->assertFalse(CustomEmoji::isValidName($veryLongName));
    }

    #[Test]
    #[TestDox('isValidName() accepts names at maximum length boundary')]
    public function is_valid_name_accepts_names_at_maximum_length_boundary(): void
    {
        config(['emoji.name_max_length' => 50]);

        $maxLengthName = str_repeat('a', 50);
        $this->assertTrue(CustomEmoji::isValidName($maxLengthName));

        $justUnderMaxName = str_repeat('a', 49);
        $this->assertTrue(CustomEmoji::isValidName($justUnderMaxName));
    }

    #[Test]
    #[TestDox('isValidName() respects custom name pattern from config')]
    public function is_valid_name_respects_custom_name_pattern_from_config(): void
    {
        // Test with custom pattern that only allows lowercase letters
        config(['emoji.name_pattern' => '/^[a-z]+$/']);
        config(['emoji.name_min_length' => 2]);
        config(['emoji.name_max_length' => 50]);

        $this->assertTrue(CustomEmoji::isValidName('lowercase'));
        $this->assertFalse(CustomEmoji::isValidName('UPPERCASE'));
        $this->assertFalse(CustomEmoji::isValidName('MixedCase'));
        $this->assertFalse(CustomEmoji::isValidName('with_underscore'));
        $this->assertFalse(CustomEmoji::isValidName('with123numbers'));
    }

    #[Test]
    #[TestDox('isValidName() respects custom minimum length from config')]
    public function is_valid_name_respects_custom_minimum_length_from_config(): void
    {
        config(['emoji.name_min_length' => 5]);
        config(['emoji.name_max_length' => 50]);

        $this->assertFalse(CustomEmoji::isValidName('abc'));
        $this->assertFalse(CustomEmoji::isValidName('abcd'));
        $this->assertTrue(CustomEmoji::isValidName('abcde'));
        $this->assertTrue(CustomEmoji::isValidName('abcdef'));
    }

    #[Test]
    #[TestDox('isValidName() respects custom maximum length from config')]
    public function is_valid_name_respects_custom_maximum_length_from_config(): void
    {
        config(['emoji.name_min_length' => 2]);
        config(['emoji.name_max_length' => 10]);

        $this->assertTrue(CustomEmoji::isValidName('short'));
        $this->assertTrue(CustomEmoji::isValidName(str_repeat('a', 10)));
        $this->assertFalse(CustomEmoji::isValidName(str_repeat('a', 11)));
    }

    #[Test]
    #[TestDox('isValidName() handles empty string correctly')]
    public function is_valid_name_handles_empty_string_correctly(): void
    {
        config(['emoji.name_min_length' => 2]);

        $this->assertFalse(CustomEmoji::isValidName(''));
    }

    #[Test]
    #[TestDox('isValidName() validates against default config pattern')]
    public function is_valid_name_validates_against_default_config_pattern(): void
    {
        config(['emoji.name_pattern' => '/^[a-zA-Z0-9_]+$/']);
        config(['emoji.name_min_length' => 2]);
        config(['emoji.name_max_length' => 50]);

        // Valid: alphanumeric and underscores
        $this->assertTrue(CustomEmoji::isValidName('valid_emoji_123'));
        $this->assertTrue(CustomEmoji::isValidName('_underscore_start'));
        $this->assertTrue(CustomEmoji::isValidName('underscore_end_'));
        $this->assertTrue(CustomEmoji::isValidName('__double_underscore__'));

        // Invalid: special characters not in pattern
        $this->assertFalse(CustomEmoji::isValidName('emoji-with-dashes'));
        $this->assertFalse(CustomEmoji::isValidName('emoji with spaces'));
        $this->assertFalse(CustomEmoji::isValidName('emoji.with.dots'));
    }

    #[Test]
    #[TestDox('isValidName() works with various edge case names')]
    #[DataProvider('edgeCaseNamesProvider')]
    public function is_valid_name_works_with_various_edge_case_names(string $name, bool $expected): void
    {
        config(['emoji.name_pattern' => '/^[a-zA-Z0-9_]+$/']);
        config(['emoji.name_min_length' => 2]);
        config(['emoji.name_max_length' => 50]);

        $this->assertEquals($expected, CustomEmoji::isValidName($name));
    }

    /**
     * @return array<string, array{name: string, expected: bool}>
     */
    public static function edgeCaseNamesProvider(): array
    {
        return [
            'only underscores' => ['name' => '__', 'expected' => true],
            'only numbers' => ['name' => '123', 'expected' => true],
            'unicode characters' => ['name' => 'émoji', 'expected' => false],
            'emoji characters' => ['name' => '😀', 'expected' => false],
            'with newline' => ['name' => "test\nname", 'expected' => false],
            'with tab' => ['name' => "test\tname", 'expected' => false],
            'with null byte' => ['name' => "test\0name", 'expected' => false],
            'leading space' => ['name' => ' test', 'expected' => false],
            'trailing space' => ['name' => 'test ', 'expected' => false],
            'parentheses' => ['name' => 'test()', 'expected' => false],
            'brackets' => ['name' => 'test[]', 'expected' => false],
            'braces' => ['name' => 'test{}', 'expected' => false],
            'quotes' => ['name' => "test'name", 'expected' => false],
            'double quotes' => ['name' => 'test"name', 'expected' => false],
        ];
    }
}
