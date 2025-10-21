<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\VideoStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VideoStatus::class)]
class VideoStatusTest extends TestCase
{
    #[Test]
    public function values_returns_all_enum_values(): void
    {
        $values = VideoStatus::values();

        $this->assertIsArray($values);
        $this->assertCount(4, $values);
        $this->assertContains('pending', $values);
        $this->assertContains('transcoding', $values);
        $this->assertContains('ready', $values);
        $this->assertContains('error', $values);
    }

    #[Test]
    public function enum_cases_exist(): void
    {
        $cases = VideoStatus::cases();

        $this->assertCount(4, $cases);
        $this->assertEquals(VideoStatus::PENDING, $cases[0]);
        $this->assertEquals(VideoStatus::TRANSCODING, $cases[1]);
        $this->assertEquals(VideoStatus::READY, $cases[2]);
        $this->assertEquals(VideoStatus::ERROR, $cases[3]);
    }

    #[Test]
    public function enum_values_are_strings(): void
    {
        $this->assertEquals('pending', VideoStatus::PENDING->value);
        $this->assertEquals('transcoding', VideoStatus::TRANSCODING->value);
        $this->assertEquals('ready', VideoStatus::READY->value);
        $this->assertEquals('error', VideoStatus::ERROR->value);
    }

    #[Test]
    public function enum_can_be_created_from_string_values(): void
    {
        $pendingFromString = VideoStatus::from('pending');
        $transcodingFromString = VideoStatus::from('transcoding');
        $readyFromString = VideoStatus::from('ready');
        $errorFromString = VideoStatus::from('error');

        $this->assertEquals(VideoStatus::PENDING, $pendingFromString);
        $this->assertEquals(VideoStatus::TRANSCODING, $transcodingFromString);
        $this->assertEquals(VideoStatus::READY, $readyFromString);
        $this->assertEquals(VideoStatus::ERROR, $errorFromString);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_values(): void
    {
        $result = VideoStatus::tryFrom('invalid_value');

        $this->assertNull($result);
    }

    #[Test]
    public function try_from_returns_enum_for_valid_values(): void
    {
        $pending = VideoStatus::tryFrom('pending');
        $transcoding = VideoStatus::tryFrom('transcoding');
        $ready = VideoStatus::tryFrom('ready');
        $error = VideoStatus::tryFrom('error');

        $this->assertEquals(VideoStatus::PENDING, $pending);
        $this->assertEquals(VideoStatus::TRANSCODING, $transcoding);
        $this->assertEquals(VideoStatus::READY, $ready);
        $this->assertEquals(VideoStatus::ERROR, $error);
    }

    #[Test]
    public function all_status_values_are_unique(): void
    {
        $values = VideoStatus::values();
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues);
    }
}
