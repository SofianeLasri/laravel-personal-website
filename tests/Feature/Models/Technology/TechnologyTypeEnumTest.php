<?php

namespace Tests\Feature\Models\Technology;

use App\Enums\TechnologyType;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TechnologyType::class)]
class TechnologyTypeEnumTest extends TestCase
{
    use RefreshDatabase;

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
    public function it_can_filter_technologies_by_type()
    {
        Technology::factory()->create(['type' => TechnologyType::FRAMEWORK]);
        Technology::factory()->create(['type' => TechnologyType::FRAMEWORK]);
        Technology::factory()->create(['type' => TechnologyType::LANGUAGE]);

        $frameworks = Technology::where('type', TechnologyType::FRAMEWORK->value)->get();
        $languages = Technology::where('type', TechnologyType::LANGUAGE->value)->get();

        $this->assertCount(2, $frameworks);
        $this->assertCount(1, $languages);
    }

    #[Test]
    public function technology_accepts_valid_enum_type()
    {
        $technology = Technology::factory()->create(['type' => TechnologyType::LIBRARY]);
        $this->assertEquals(TechnologyType::LIBRARY, $technology->type);

        $technologyFromDb = Technology::find($technology->id);
        $this->assertEquals(TechnologyType::LIBRARY, $technologyFromDb->type);
    }
}
