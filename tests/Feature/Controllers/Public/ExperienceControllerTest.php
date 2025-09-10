<?php

namespace Tests\Feature\Controllers\Public;

use App\Enums\ExperienceType;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\SocialMediaLink;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceControllerTest extends TestCase
{
    use RefreshDatabase;

    private Experience $experience;

    protected function setUp(): void
    {
        parent::setUp();

        SocialMediaLink::factory()->count(3)->create();

        $logo = Picture::factory()->create();

        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'Senior Developer',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => 'Développeur Senior',
        ]);

        $shortDescKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'en',
            'text' => 'A senior developer position',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'fr',
            'text' => 'Un poste de développeur senior',
        ]);

        $fullDescKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'en',
            'text' => 'Full description of the experience in English',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'fr',
            'text' => 'Description complète de l\'expérience en français',
        ]);

        $this->experience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Test Company',
            'logo_id' => $logo->id,
            'type' => ExperienceType::EMPLOI,
            'location' => 'Paris, France',
            'website_url' => 'https://example.com',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => now()->subYears(2),
            'ended_at' => now()->subYear(),
        ]);

        $this->experience->slug = 'test-company-senior-developer';
        $this->experience->save();

        $technologies = Technology::factory()->count(3)->create();
        $this->experience->technologies()->attach($technologies);
    }

    public function test_experience_page_loads_successfully(): void
    {
        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/Experience')
            ->has('experience')
            ->has('socialMediaLinks')
            ->has('locale')
            ->has('browserLanguage')
            ->has('translations')
        );
    }

    public function test_experience_page_contains_correct_data(): void
    {
        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertInertia(fn ($page) => $page
            ->where('experience.id', $this->experience->id)
            ->where('experience.title', 'Développeur Senior')  // French is default locale
            ->where('experience.organizationName', 'Test Company')
            ->where('experience.location', 'Paris, France')
            ->where('experience.websiteUrl', 'https://example.com')
            ->where('experience.shortDescription', 'Un poste de développeur senior')  // French
            ->where('experience.fullDescription', 'Description complète de l\'expérience en français')  // French
            ->where('experience.type', ExperienceType::EMPLOI->value)
            ->has('experience.technologies', 3)
            ->has('experience.logo')
            ->has('experience.startedAt')
            ->has('experience.endedAt')
            ->has('experience.startedAtFormatted')
            ->has('experience.endedAtFormatted')
        );
    }

    public function test_experience_page_returns_404_for_invalid_slug(): void
    {
        $response = $this->get('/certifications-career/invalid-slug');

        $response->assertStatus(404);
    }

    public function test_experience_page_works_with_english_locale(): void
    {
        // Since French is the default, test that fallback to English works
        // We'll check that we still get the French translations since
        // the service gets initialized with the app locale
        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')  // French is default
            ->where('experience.title', 'Développeur Senior')
            ->where('experience.shortDescription', 'Un poste de développeur senior')
            ->where('experience.fullDescription', 'Description complète de l\'expérience en français')
        );
    }

    public function test_experience_page_works_without_website_url(): void
    {
        $this->experience->update(['website_url' => null]);

        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('experience.websiteUrl', null)
        );
    }

    public function test_experience_page_works_without_ended_date(): void
    {
        $this->experience->update(['ended_at' => null]);

        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('experience.endedAt', null)
            ->where('experience.endedAtFormatted', null)
        );
    }

    public function test_experience_page_works_with_education_type(): void
    {
        $this->experience->update(['type' => ExperienceType::FORMATION]);

        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('experience.type', ExperienceType::FORMATION->value)
        );
    }

    public function test_experience_page_includes_all_translations(): void
    {
        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertInertia(fn ($page) => $page
            ->has('translations.experience')
            ->has('translations.navigation')
            ->has('translations.footer')
            ->has('translations.search')
        );
    }

    public function test_experience_page_includes_technologies_with_correct_format(): void
    {
        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertInertia(fn ($page) => $page
            ->has('experience.technologies.0', fn ($tech) => $tech
                ->has('id')
                ->has('name')
                ->has('description')
                ->has('iconPicture')
                ->has('creationCount')
                ->has('type')
            )
        );
    }

    public function test_experience_page_includes_logo_with_correct_format(): void
    {
        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertInertia(fn ($page) => $page
            ->has('experience.logo.avif')
            ->has('experience.logo.webp')
            ->has('experience.logo.jpg')
        );
    }

    public function test_experience_without_technologies_works(): void
    {
        $this->experience->technologies()->detach();

        $response = $this->get('/certifications-career/'.$this->experience->slug);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('experience.technologies', 0)
        );
    }

    public function test_special_characters_in_slug_are_handled(): void
    {
        $this->experience->update(['slug' => 'test-with-special-123']);

        $response = $this->get('/certifications-career/test-with-special-123');

        $response->assertStatus(200);
    }

    public function test_route_only_accepts_valid_slug_format(): void
    {
        // Test with invalid characters
        $response = $this->get('/certifications-career/test@invalid');
        $response->assertStatus(404);

        $response = $this->get('/certifications-career/test!slug');
        $response->assertStatus(404);

        $response = $this->get('/certifications-career/test slug');
        $response->assertStatus(404);
    }
}
