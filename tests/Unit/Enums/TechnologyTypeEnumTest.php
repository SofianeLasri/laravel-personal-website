<?php

namespace Tests\Unit\Enums;

use App\Enums\TechnologyType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TechnologyType::class)]
class TechnologyTypeEnumTest extends TestCase
{

    #[Test]
    public function it_can_convert_enum_to_label()
    {
        $this->assertEquals('Framework', TechnologyType::FRAMEWORK->label());
        $this->assertEquals('Bibliothèque', TechnologyType::LIBRARY->label());
        $this->assertEquals('Langage', TechnologyType::LANGUAGE->label());
        $this->assertEquals('Autre', TechnologyType::OTHER->label());
    }

    #[Test]
    public function it_can_get_all_enum_values()
    {
        $values = TechnologyType::values();

        $this->assertContains('framework', $values);
        $this->assertContains('library', $values);
        $this->assertContains('language', $values);
        $this->assertContains('other', $values);
    }

    #[Test]
    public function it_has_correct_enum_values()
    {
        $this->assertEquals('framework', TechnologyType::FRAMEWORK->value);
        $this->assertEquals('library', TechnologyType::LIBRARY->value);
        $this->assertEquals('language', TechnologyType::LANGUAGE->value);
        $this->assertEquals('other', TechnologyType::OTHER->value);
    }
}
