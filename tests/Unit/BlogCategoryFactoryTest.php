<?php

namespace Tests\Unit;

use App\Models\BlogCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogCategoryFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_category_with_custom_names(): void
    {
        $category = BlogCategory::factory()->withNames([
            'fr' => 'Technologie',
            'en' => 'Technology',
        ])->create();

        $this->assertNotNull($category);
        $this->assertStringContainsString('technologie', $category->slug);

        // Vérifier que les traductions sont bien créées
        $translations = $category->nameTranslationKey->translations;

        $frenchTranslation = $translations->where('locale', 'fr')->first();
        $englishTranslation = $translations->where('locale', 'en')->first();

        $this->assertNotNull($frenchTranslation);
        $this->assertEquals('Technologie', $frenchTranslation->text);

        $this->assertNotNull($englishTranslation);
        $this->assertEquals('Technology', $englishTranslation->text);
    }

    public function test_can_create_category_with_french_name_only(): void
    {
        $category = BlogCategory::factory()->withFrenchName('Science')->create();

        $this->assertNotNull($category);
        $this->assertStringContainsString('science', $category->slug);

        $translations = $category->nameTranslationKey->translations;

        $frenchTranslation = $translations->where('locale', 'fr')->first();
        $englishTranslation = $translations->where('locale', 'en')->first();

        $this->assertNotNull($frenchTranslation);
        $this->assertEquals('Science', $frenchTranslation->text);

        $this->assertNotNull($englishTranslation);
        $this->assertEquals('', $englishTranslation->text); // Vide par défaut
    }

    public function test_can_create_category_with_both_french_and_english_names(): void
    {
        $category = BlogCategory::factory()->withFrenchName('Arts', 'Arts')->create();

        $this->assertNotNull($category);

        $translations = $category->nameTranslationKey->translations;

        $frenchTranslation = $translations->where('locale', 'fr')->first();
        $englishTranslation = $translations->where('locale', 'en')->first();

        $this->assertEquals('Arts', $frenchTranslation->text);
        $this->assertEquals('Arts', $englishTranslation->text);
    }

    public function test_can_create_category_with_english_name_method(): void
    {
        $category = BlogCategory::factory()->withEnglishName('Gaming', 'Jeux Vidéo')->create();

        $this->assertNotNull($category);

        $translations = $category->nameTranslationKey->translations;

        $frenchTranslation = $translations->where('locale', 'fr')->first();
        $englishTranslation = $translations->where('locale', 'en')->first();

        $this->assertEquals('Jeux Vidéo', $frenchTranslation->text);
        $this->assertEquals('Gaming', $englishTranslation->text);
    }

    public function test_slug_is_generated_from_french_name_when_available(): void
    {
        $category = BlogCategory::factory()->withNames([
            'en' => 'Technology',
            'fr' => 'Technologie',
        ])->create();

        // Le slug doit être basé sur le nom français
        $this->assertStringContainsString('technologie', $category->slug);
    }

    public function test_slug_uses_first_name_when_no_french_name(): void
    {
        $category = BlogCategory::factory()->withNames([
            'en' => 'Technology',
        ])->create();

        // Le slug doit être basé sur le nom anglais car pas de français
        $this->assertStringContainsString('technology', $category->slug);
    }
}
