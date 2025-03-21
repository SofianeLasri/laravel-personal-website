<?php

namespace Models\Experience;

use App\Enums\ExperienceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ExperienceType::class)]
class ExperienceTypeEnumTest extends TestCase
{
    use RefreshDatabase;

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
}
