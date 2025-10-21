<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\GameReviewRating;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GameReviewRating::class)]
class GameReviewRatingTest extends TestCase
{
    #[Test]
    public function label_returns_correct_french_labels(): void
    {
        $this->assertEquals('Review positive', GameReviewRating::POSITIVE->label());
        $this->assertEquals('Review négative', GameReviewRating::NEGATIVE->label());
    }

    #[Test]
    public function label_en_returns_correct_english_labels(): void
    {
        $this->assertEquals('Positive review', GameReviewRating::POSITIVE->labelEn());
        $this->assertEquals('Negative review', GameReviewRating::NEGATIVE->labelEn());
    }

    #[Test]
    public function values_returns_all_enum_values(): void
    {
        $values = GameReviewRating::values();

        $this->assertIsArray($values);
        $this->assertCount(2, $values);
        $this->assertContains('positive', $values);
        $this->assertContains('negative', $values);
    }

    #[Test]
    public function labels_returns_all_french_labels(): void
    {
        $labels = GameReviewRating::labels();

        $this->assertIsArray($labels);
        $this->assertCount(2, $labels);
        $this->assertContains('Review positive', $labels);
        $this->assertContains('Review négative', $labels);
    }

    #[Test]
    public function enum_cases_exist(): void
    {
        $cases = GameReviewRating::cases();

        $this->assertCount(2, $cases);
        $this->assertEquals(GameReviewRating::POSITIVE, $cases[0]);
        $this->assertEquals(GameReviewRating::NEGATIVE, $cases[1]);
    }

    #[Test]
    public function enum_values_are_strings(): void
    {
        $this->assertEquals('positive', GameReviewRating::POSITIVE->value);
        $this->assertEquals('negative', GameReviewRating::NEGATIVE->value);
    }

    #[Test]
    public function enum_can_be_created_from_string_values(): void
    {
        $positiveFromString = GameReviewRating::from('positive');
        $negativeFromString = GameReviewRating::from('negative');

        $this->assertEquals(GameReviewRating::POSITIVE, $positiveFromString);
        $this->assertEquals(GameReviewRating::NEGATIVE, $negativeFromString);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        $result = GameReviewRating::tryFrom('invalid_value');

        $this->assertNull($result);
    }

    #[Test]
    public function try_from_returns_enum_for_valid_values(): void
    {
        $positive = GameReviewRating::tryFrom('positive');
        $negative = GameReviewRating::tryFrom('negative');

        $this->assertEquals(GameReviewRating::POSITIVE, $positive);
        $this->assertEquals(GameReviewRating::NEGATIVE, $negative);
    }
}
