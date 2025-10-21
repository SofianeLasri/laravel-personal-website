<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\VideoVisibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VideoVisibility::class)]
class VideoVisibilityTest extends TestCase
{
    #[Test]
    public function values_returns_all_enum_values(): void
    {
        $values = VideoVisibility::values();

        $this->assertIsArray($values);
        $this->assertCount(2, $values);
        $this->assertContains('public', $values);
        $this->assertContains('private', $values);
    }

    #[Test]
    public function enum_cases_exist(): void
    {
        $cases = VideoVisibility::cases();

        $this->assertCount(2, $cases);
        $this->assertEquals(VideoVisibility::PUBLIC, $cases[0]);
        $this->assertEquals(VideoVisibility::PRIVATE, $cases[1]);
    }

    #[Test]
    public function enum_values_are_strings(): void
    {
        $this->assertEquals('public', VideoVisibility::PUBLIC->value);
        $this->assertEquals('private', VideoVisibility::PRIVATE->value);
    }

    #[Test]
    public function enum_can_be_created_from_string_values(): void
    {
        $publicFromString = VideoVisibility::from('public');
        $privateFromString = VideoVisibility::from('private');

        $this->assertEquals(VideoVisibility::PUBLIC, $publicFromString);
        $this->assertEquals(VideoVisibility::PRIVATE, $privateFromString);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        $result = VideoVisibility::tryFrom('invalid_value');

        $this->assertNull($result);
    }

    #[Test]
    public function try_from_returns_enum_for_valid_values(): void
    {
        $public = VideoVisibility::tryFrom('public');
        $private = VideoVisibility::tryFrom('private');

        $this->assertEquals(VideoVisibility::PUBLIC, $public);
        $this->assertEquals(VideoVisibility::PRIVATE, $private);
    }

    #[Test]
    public function all_visibility_values_are_unique(): void
    {
        $values = VideoVisibility::values();
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues);
    }
}
