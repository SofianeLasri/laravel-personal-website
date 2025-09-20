<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\BlogPostType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlogPostType::class)]
class BlogPostTypeTest extends TestCase
{
    #[Test]
    public function test_label_returns_correct_values(): void
    {
        $this->assertEquals('Article', BlogPostType::ARTICLE->label());
        $this->assertEquals('Critique de jeu', BlogPostType::GAME_REVIEW->label());
    }

    #[Test]
    public function test_icon_returns_correct_values(): void
    {
        $this->assertEquals('newspaper', BlogPostType::ARTICLE->icon());
        $this->assertEquals('gamepad', BlogPostType::GAME_REVIEW->icon());
    }

    #[Test]
    public function test_values_returns_all_enum_values(): void
    {
        $values = BlogPostType::values();

        $this->assertIsArray($values);
        $this->assertCount(2, $values);
        $this->assertContains('article', $values);
        $this->assertContains('game_review', $values);
    }

    #[Test]
    public function test_enum_cases_exist(): void
    {
        $cases = BlogPostType::cases();

        $this->assertCount(2, $cases);
        $this->assertEquals(BlogPostType::ARTICLE, $cases[0]);
        $this->assertEquals(BlogPostType::GAME_REVIEW, $cases[1]);
    }

    #[Test]
    public function test_enum_values_are_strings(): void
    {
        $this->assertEquals('article', BlogPostType::ARTICLE->value);
        $this->assertEquals('game_review', BlogPostType::GAME_REVIEW->value);
    }

    #[Test]
    public function test_enum_can_be_created_from_string_values(): void
    {
        $articleFromString = BlogPostType::from('article');
        $gameReviewFromString = BlogPostType::from('game_review');

        $this->assertEquals(BlogPostType::ARTICLE, $articleFromString);
        $this->assertEquals(BlogPostType::GAME_REVIEW, $gameReviewFromString);
    }

    #[Test]
    public function test_try_from_returns_null_for_invalid_values(): void
    {
        $result = BlogPostType::tryFrom('invalid_value');

        $this->assertNull($result);
    }

    #[Test]
    public function test_try_from_returns_enum_for_valid_values(): void
    {
        $article = BlogPostType::tryFrom('article');
        $gameReview = BlogPostType::tryFrom('game_review');

        $this->assertEquals(BlogPostType::ARTICLE, $article);
        $this->assertEquals(BlogPostType::GAME_REVIEW, $gameReview);
    }
}
