<?php

namespace Tests\Feature\Http\Controllers\Public;

use App\Enums\ExperienceType;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\SocialMediaLink;
use App\Models\Technology;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationsCareerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SocialMediaLink::factory()->count(3)->create();
    }

    public function test_certifications_career_page_includes_experience_slugs(): void
    {
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
            'text' => 'Full description',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'fr',
            'text' => 'Description complète',
        ]);

        $workExperience = Experience::factory()->create([
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
        $workExperience->slug = 'test-company-work';
        $workExperience->save();

        $educationExperience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Test University',
            'logo_id' => $logo->id,
            'type' => ExperienceType::FORMATION,
            'location' => 'Lyon, France',
            'website_url' => 'https://university.example.com',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => now()->subYears(4),
            'ended_at' => now()->subYears(2),
        ]);
        $educationExperience->slug = 'test-university-formation';
        $educationExperience->save();

        $technologies = Technology::factory()->count(2)->create();
        $workExperience->technologies()->attach($technologies);
        $educationExperience->technologies()->attach($technologies);

        $response = $this->get('/certifications-career');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/CertificationsCareer')
            ->has('workExperiences', 1)
            ->has('educationExperiences', 1)
            ->where('workExperiences.0.slug', 'test-company-work')
            ->where('educationExperiences.0.slug', 'test-university-formation')
        );
    }

    public function test_experience_links_are_accessible_from_certifications_career_page(): void
    {
        $logo = Picture::factory()->create();

        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'fr',
            'text' => 'Développeur',
        ]);

        $shortDescKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'fr',
            'text' => 'Description courte',
        ]);

        $fullDescKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'fr',
            'text' => 'Description complète',
        ]);

        $experience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Test Org',
            'logo_id' => $logo->id,
            'type' => ExperienceType::EMPLOI,
            'location' => 'Paris',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => now()->subYear(),
        ]);
        $experience->slug = 'test-org-slug';
        $experience->save();

        $response = $this->get('/certifications-career');
        $response->assertStatus(200);

        $detailResponse = $this->get('/certifications-career/test-org-slug');
        $detailResponse->assertStatus(200);
        $detailResponse->assertInertia(fn ($page) => $page
            ->component('public/Experience')
            ->where('experience.id', $experience->id)
        );
    }
}
