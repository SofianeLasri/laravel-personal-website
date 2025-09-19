<?php

namespace Tests\Unit\Models\Picture;

use App\Models\Picture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

#[CoversClass(Picture::class)]
class PictureTest extends TestCase
{
    #[Test]
    #[TestDox('hasValidOriginalPath returns true when path_original is valid')]
    public function test_has_valid_original_path_returns_true_when_path_is_valid(): void
    {
        $picture = new Picture(['path_original' => 'uploads/test.jpg']);

        $this->assertTrue($picture->hasValidOriginalPath());
    }

    #[Test]
    #[TestDox('hasValidOriginalPath returns false when path_original is null')]
    public function test_has_valid_original_path_returns_false_when_path_is_null(): void
    {
        $picture = new Picture(['path_original' => null]);

        $this->assertFalse($picture->hasValidOriginalPath());
    }

    #[Test]
    #[TestDox('hasValidOriginalPath returns false when path_original is empty string')]
    public function test_has_valid_original_path_returns_false_when_path_is_empty(): void
    {
        $picture = new Picture(['path_original' => '']);

        $this->assertFalse($picture->hasValidOriginalPath());
    }

    #[Test]
    #[TestDox('hasValidOriginalPath returns true even when path_original contains spaces')]
    public function test_has_valid_original_path_returns_true_when_path_contains_spaces(): void
    {
        $picture = new Picture(['path_original' => '   uploads/test file.jpg   ']);

        // The method only checks for null and empty, not whitespace trimming
        $this->assertTrue($picture->hasValidOriginalPath());
    }
}