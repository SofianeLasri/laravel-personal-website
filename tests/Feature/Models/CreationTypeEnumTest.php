<?php

namespace Tests\Feature\Models;

use App\Enums\CreationType;
use App\Models\Creation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationType::class)]
class CreationTypeEnumTest extends TestCase
{
    use RefreshDatabase;

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
    public function it_can_filter_creations_by_type()
    {
        Creation::factory()->create(['type' => CreationType::WEBSITE]);
        Creation::factory()->create(['type' => CreationType::WEBSITE]);
        Creation::factory()->create(['type' => CreationType::GAME]);

        $websites = Creation::where('type', CreationType::WEBSITE->value)->get();
        $games = Creation::where('type', CreationType::GAME->value)->get();

        $this->assertCount(2, $websites);
        $this->assertCount(1, $games);
    }

    #[Test]
    public function creation_accepts_valid_enum_type()
    {
        $creation = Creation::factory()->create(['type' => CreationType::TOOL]);
        $this->assertEquals(CreationType::TOOL, $creation->type);

        $creationFromDb = Creation::find($creation->id);
        $this->assertEquals(CreationType::TOOL, $creationFromDb->type);
    }
}
