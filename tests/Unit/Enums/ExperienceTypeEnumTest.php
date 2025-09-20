<?php

namespace Tests\Unit\Enums;

use App\Enums\ExperienceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExperienceType::class)]
class ExperienceTypeEnumTest extends TestCase
{
    #[Test]
    public function it_can_convert_enum_to_label()
    {
        $this->assertEquals('Formation', ExperienceType::FORMATION->label());
        $this->assertEquals('Emploi', ExperienceType::EMPLOI->label());
    }

    #[Test]
    public function it_can_get_all_enum_values()
    {
        $values = ExperienceType::values();

        $this->assertContains('formation', $values);
        $this->assertContains('emploi', $values);
    }

    #[Test]
    public function it_has_correct_enum_values()
    {
        $this->assertEquals('formation', ExperienceType::FORMATION->value);
        $this->assertEquals('emploi', ExperienceType::EMPLOI->value);
    }
}
