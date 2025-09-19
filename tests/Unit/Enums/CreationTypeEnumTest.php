<?php

namespace Tests\Unit\Enums;

use App\Enums\CreationType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreationType::class)]
class CreationTypeEnumTest extends TestCase
{

    #[Test]
    public function it_can_convert_enum_to_label()
    {
        $this->assertEquals('Site internet', CreationType::WEBSITE->label());
        $this->assertEquals('Jeu', CreationType::GAME->label());
        $this->assertEquals('Bibliothèque', CreationType::LIBRARY->label());
        $this->assertEquals('Outil', CreationType::TOOL->label());
        $this->assertEquals('Carte', CreationType::MAP->label());
        $this->assertEquals('Autre', CreationType::OTHER->label());
    }

    #[Test]
    public function it_can_get_all_enum_values()
    {
        $values = CreationType::values();

        $this->assertContains('portfolio', $values);
        $this->assertContains('game', $values);
        $this->assertContains('library', $values);
        $this->assertContains('website', $values);
        $this->assertContains('tool', $values);
        $this->assertContains('map', $values);
        $this->assertContains('other', $values);
    }

    #[Test]
    public function it_has_correct_enum_values()
    {
        $this->assertEquals('portfolio', CreationType::PORTFOLIO->value);
        $this->assertEquals('website', CreationType::WEBSITE->value);
        $this->assertEquals('game', CreationType::GAME->value);
        $this->assertEquals('library', CreationType::LIBRARY->value);
        $this->assertEquals('tool', CreationType::TOOL->value);
        $this->assertEquals('map', CreationType::MAP->value);
        $this->assertEquals('other', CreationType::OTHER->value);
    }
}
