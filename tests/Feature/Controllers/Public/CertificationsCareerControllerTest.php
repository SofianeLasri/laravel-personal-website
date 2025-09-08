<?php

namespace Tests\Feature\Controllers\Public;

use App\Enums\ExperienceType;
use App\Models\Certification;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\SocialMediaLink;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationsCareerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_certifications_career_page_loads_successfully()
    {
        $response = $this->get('/certifications-career');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('public/CertificationsCareer')
            ->has('socialMediaLinks')
            ->has('locale')
            ->has('translations')
            ->has('certifications')
            ->has('educationExperiences')
            ->has('workExperiences')
        );
    }

    public function test_certifications_career_page_displays_certifications_data()
    {
        $picture = Picture::factory()->create();

        $certification = Certification::factory()->create([
            'name' => 'Laravel Expert Certification',
            'level' => 'Expert',
            'score' => '95%',
            'date' => '2025-01-15',
            'link' => 'https://example.com/cert',
            'picture_id' => $picture->id,
        ]);

        $response = $this->get('/certifications-career');

        $response->assertInertia(fn ($page) => $page->where('certifications.0.id', $certification->id)
            ->where('certifications.0.name', 'Laravel Expert Certification')
            ->where('certifications.0.level', 'Expert')
            ->where('certifications.0.score', '95%')
            ->where('certifications.0.link', 'https://example.com/cert')
            ->has('certifications.0.picture')
            ->has('certifications.0.dateFormatted')
        );
    }

    public function test_certifications_career_page_displays_experiences_data()
    {
        $picture = Picture::factory()->create();

        // Create translation keys
        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'Software Developer',
        ]);

        $shortDescKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $shortDescKey->id,
            'locale' => 'en',
            'text' => 'Short description',
        ]);

        $fullDescKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'en',
            'text' => 'Full description',
        ]);

        // Create education experience
        $educationExperience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Test University',
            'logo_id' => $picture->id,
            'type' => ExperienceType::FORMATION,
            'location' => 'Paris, France',
            'website_url' => 'https://university.com',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => '2020-09-01',
            'ended_at' => '2023-06-30',
        ]);

        // Create work experience
        $workExperience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Tech Company',
            'logo_id' => $picture->id,
            'type' => ExperienceType::EMPLOI,
            'location' => 'Lyon, France',
            'website_url' => 'https://techcompany.com',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => '2023-07-01',
            'ended_at' => null,
        ]);

        $response = $this->get('/certifications-career');

        $response->assertInertia(fn ($page) => $page->where('educationExperiences.0.id', $educationExperience->id)
            ->where('educationExperiences.0.title', 'Software Developer')
            ->where('educationExperiences.0.organizationName', 'Test University')
            ->where('educationExperiences.0.type', ExperienceType::FORMATION)
            ->where('workExperiences.0.id', $workExperience->id)
            ->where('workExperiences.0.organizationName', 'Tech Company')
            ->where('workExperiences.0.type', ExperienceType::EMPLOI)
        );
    }

    public function test_certifications_career_page_includes_social_media_links()
    {
        $socialMediaLink = SocialMediaLink::factory()->create([
            'name' => 'LinkedIn',
            'url' => 'https://linkedin.com/in/test',
            'icon_svg' => '<svg>test</svg>',
        ]);

        $response = $this->get('/certifications-career');

        $response->assertInertia(fn ($page) => $page->where('socialMediaLinks.0.id', $socialMediaLink->id)
            ->where('socialMediaLinks.0.name', 'LinkedIn')
            ->where('socialMediaLinks.0.url', 'https://linkedin.com/in/test')
        );
    }

    public function test_certifications_career_page_includes_translations()
    {
        $response = $this->get('/certifications-career');

        $response->assertInertia(fn ($page) => $page->has('translations.navigation')
            ->has('translations.footer')
            ->has('translations.career')
        );
    }

    public function test_certifications_career_page_handles_empty_data()
    {
        $response = $this->get('/certifications-career');

        $response->assertInertia(fn ($page) => $page->where('certifications', [])
            ->where('educationExperiences', [])
            ->where('workExperiences', [])
        );
    }
}
