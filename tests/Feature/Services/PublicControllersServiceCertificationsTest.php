<?php

namespace Tests\Feature\Services;

use App\Enums\ExperienceType;
use App\Models\Certification;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Services\CustomEmojiResolverService;
use App\Services\PublicControllersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PublicControllersService::class)]
class PublicControllersServiceCertificationsTest extends TestCase
{
    use RefreshDatabase;

    private PublicControllersService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PublicControllersService(new CustomEmojiResolverService);
    }

    #[Test]
    public function test_get_certifications_returns_formatted_certifications(): void
    {
        $picture = Picture::factory()->create();

        $certification1 = Certification::factory()->create([
            'name' => 'Laravel Certification',
            'level' => 'Senior Developer',
            'score' => '88.9%',
            'date' => '2025-02-27',
            'link' => 'https://example.com/cert1',
            'picture_id' => $picture->id,
        ]);

        $certification2 = Certification::factory()->create([
            'name' => 'TOEIC',
            'level' => 'B1',
            'score' => '775/990',
            'date' => '2023-06-15',
            'link' => null,
            'picture_id' => null,
        ]);

        $certifications = $this->service->getCertifications();

        $this->assertCount(2, $certifications);

        // Most recent first (ordered by date desc)
        $firstCert = $certifications->first();
        $this->assertEquals($certification1->id, $firstCert['id']);
        $this->assertEquals('Laravel Certification', $firstCert['name']);
        $this->assertEquals('Senior Developer', $firstCert['level']);
        $this->assertEquals('88.9%', $firstCert['score']);
        $this->assertEquals('2025-02-27', $firstCert['date']);
        $this->assertEquals('https://example.com/cert1', $firstCert['link']);
        $this->assertNotNull($firstCert['picture']);
        $this->assertNotNull($firstCert['dateFormatted']);

        $secondCert = $certifications->last();
        $this->assertEquals($certification2->id, $secondCert['id']);
        $this->assertEquals('TOEIC', $secondCert['name']);
        $this->assertNull($secondCert['link']);
        $this->assertNull($secondCert['picture']);
    }

    #[Test]
    public function test_get_experiences_by_type_returns_filtered_experiences(): void
    {
        $picture = Picture::factory()->create();

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

        $workExperience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Tech Company',
            'logo_id' => $picture->id,
            'type' => ExperienceType::EMPLOI,
            'location' => 'Paris, France',
            'website_url' => 'https://example.com',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => '2022-04-01',
            'ended_at' => null,
        ]);

        Experience::factory()->create([
            'type' => ExperienceType::FORMATION,
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'University',
            'logo_id' => $picture->id,
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
        ]);

        $workExperiences = $this->service->getExperiencesByType(ExperienceType::EMPLOI);
        $educationExperiences = $this->service->getExperiencesByType(ExperienceType::FORMATION);

        $this->assertCount(1, $workExperiences);
        $this->assertCount(1, $educationExperiences);

        $workExp = $workExperiences->first();
        $this->assertEquals($workExperience->id, $workExp['id']);
        $this->assertEquals('Software Developer', $workExp['title']);
        $this->assertEquals('Tech Company', $workExp['organizationName']);
        $this->assertEquals('Paris, France', $workExp['location']);
        $this->assertEquals('https://example.com', $workExp['websiteUrl']);
        $this->assertEquals(ExperienceType::EMPLOI, $workExp['type']);
        $this->assertNotNull($workExp['logo']);
        $this->assertIsArray($workExp['technologies']);
    }

    #[Test]
    public function test_get_certifications_career_data_returns_complete_data(): void
    {
        $picture = Picture::factory()->create();

        // Create certification
        Certification::factory()->create([
            'name' => 'Test Certification',
            'picture_id' => $picture->id,
        ]);

        // Create translation keys for experiences
        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'Test Title',
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

        // Create experiences
        Experience::factory()->create([
            'type' => ExperienceType::FORMATION,
            'title_translation_key_id' => $titleKey->id,
            'logo_id' => $picture->id,
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
        ]);

        Experience::factory()->create([
            'type' => ExperienceType::EMPLOI,
            'title_translation_key_id' => $titleKey->id,
            'logo_id' => $picture->id,
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
        ]);

        $data = $this->service->getCertificationsCareerData();

        $this->assertArrayHasKey('certifications', $data);
        $this->assertArrayHasKey('educationExperiences', $data);
        $this->assertArrayHasKey('workExperiences', $data);

        $this->assertCount(1, $data['certifications']);
        $this->assertCount(1, $data['educationExperiences']);
        $this->assertCount(1, $data['workExperiences']);
    }

    #[Test]
    public function test_format_certification_for_ssr_returns_correct_format(): void
    {
        $picture = Picture::factory()->create();

        $certification = Certification::factory()->create([
            'name' => 'Test Certification',
            'level' => 'Expert',
            'score' => '95%',
            'date' => '2025-01-15',
            'link' => 'https://example.com/cert',
            'picture_id' => $picture->id,
        ]);

        $formatted = $this->service->formatCertificationForSSR($certification);

        $this->assertEquals($certification->id, $formatted['id']);
        $this->assertEquals('Test Certification', $formatted['name']);
        $this->assertEquals('Expert', $formatted['level']);
        $this->assertEquals('95%', $formatted['score']);
        $this->assertEquals('2025-01-15', $formatted['date']);
        $this->assertEquals('https://example.com/cert', $formatted['link']);
        $this->assertNotNull($formatted['picture']);
        $this->assertNotNull($formatted['dateFormatted']);

        // Check picture format
        $this->assertArrayHasKey('filename', $formatted['picture']);
        $this->assertArrayHasKey('avif', $formatted['picture']);
        $this->assertArrayHasKey('webp', $formatted['picture']);
    }

    #[Test]
    public function test_format_experience_for_ssr_returns_correct_format(): void
    {
        $picture = Picture::factory()->create();

        $titleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $titleKey->id,
            'locale' => 'en',
            'text' => 'Software Engineer',
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

        $experience = Experience::factory()->create([
            'title_translation_key_id' => $titleKey->id,
            'organization_name' => 'Tech Corp',
            'logo_id' => $picture->id,
            'type' => ExperienceType::EMPLOI,
            'location' => 'New York, USA',
            'website_url' => 'https://techcorp.com',
            'short_description_translation_key_id' => $shortDescKey->id,
            'full_description_translation_key_id' => $fullDescKey->id,
            'started_at' => '2023-01-01',
            'ended_at' => '2024-01-01',
        ]);

        $formatted = $this->service->formatExperienceForSSR($experience);

        $this->assertEquals($experience->id, $formatted['id']);
        $this->assertEquals('Software Engineer', $formatted['title']);
        $this->assertEquals('Tech Corp', $formatted['organizationName']);
        $this->assertEquals('New York, USA', $formatted['location']);
        $this->assertEquals('https://techcorp.com', $formatted['websiteUrl']);
        $this->assertEquals('Short description', $formatted['shortDescription']);
        $this->assertEquals('Full description', $formatted['fullDescription']);
        $this->assertEquals(ExperienceType::EMPLOI, $formatted['type']);
        $this->assertEquals('2023-01-01', $formatted['startedAt']);
        $this->assertEquals('2024-01-01', $formatted['endedAt']);
        $this->assertNotNull($formatted['startedAtFormatted']);
        $this->assertNotNull($formatted['endedAtFormatted']);
        $this->assertNotNull($formatted['logo']);
        $this->assertIsArray($formatted['technologies']);
    }
}
