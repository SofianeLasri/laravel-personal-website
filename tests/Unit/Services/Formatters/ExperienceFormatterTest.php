<?php

namespace Tests\Unit\Services\Formatters;

use App\Enums\ExperienceType;
use App\Enums\TechnologyType;
use App\Models\Certification;
use App\Models\Experience;
use App\Models\Picture;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\TranslationKey;
use App\Services\Formatters\ExperienceFormatter;
use App\Services\Formatters\MediaFormatter;
use App\Services\Formatters\TranslationHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ExperienceFormatter::class)]
class ExperienceFormatterTest extends TestCase
{
    private ExperienceFormatter $formatter;

    private MediaFormatter&MockInterface $mediaFormatter;

    private TranslationHelper&MockInterface $translationHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaFormatter = Mockery::mock(MediaFormatter::class);
        $this->translationHelper = Mockery::mock(TranslationHelper::class);

        $this->formatter = new ExperienceFormatter(
            $this->mediaFormatter,
            $this->translationHelper,
        );

        $this->formatter->setCreationCountByTechnology([1 => 5, 2 => 3]);
    }

    #[Test]
    public function it_formats_experience(): void
    {
        $experience = $this->createMockExperience();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn(
                'Software Developer',
                'Short description',
                'Full description',
                'PHP description'
            );

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2022', 'Décembre 2024');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'logo.jpg']);

        $result = $this->formatter->formatExperience($experience);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Software Developer', $result['title']);
        $this->assertEquals('Acme Corp', $result['organizationName']);
        $this->assertEquals('acme-corp', $result['slug']);
        $this->assertEquals('Paris', $result['location']);
        $this->assertEquals('https://acme.com', $result['websiteUrl']);
        $this->assertEquals('Short description', $result['shortDescription']);
        $this->assertEquals('Full description', $result['fullDescription']);
        $this->assertEquals(ExperienceType::EMPLOI, $result['type']);
        $this->assertEquals('2022-01-15', $result['startedAt']);
        $this->assertEquals('2024-12-01', $result['endedAt']);
        $this->assertEquals('Janvier 2022', $result['startedAtFormatted']);
        $this->assertEquals('Décembre 2024', $result['endedAtFormatted']);
        $this->assertArrayHasKey('technologies', $result);
        $this->assertArrayHasKey('logo', $result);
    }

    #[Test]
    public function it_formats_experience_without_optional_fields(): void
    {
        $experience = $this->createMockExperienceMinimal();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Developer', 'Short desc', 'Full desc');

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2022', null);

        $result = $this->formatter->formatExperience($experience);

        $this->assertNull($result['logo']);
        $this->assertNull($result['websiteUrl']);
        $this->assertNull($result['endedAt']);
        $this->assertNull($result['endedAtFormatted']);
        $this->assertEmpty($result['technologies']);
    }

    #[Test]
    public function it_formats_certification(): void
    {
        $certification = $this->createMockCertification();

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Mars 2023');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'cert.jpg']);

        $result = $this->formatter->formatCertification($certification);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('AWS Certified', $result['name']);
        $this->assertEquals('Professional', $result['level']);
        $this->assertEquals('950/1000', $result['score']);
        $this->assertEquals('2023-03-15', $result['date']);
        $this->assertEquals('Mars 2023', $result['dateFormatted']);
        $this->assertEquals('https://aws.amazon.com/certification', $result['link']);
        $this->assertEquals(['filename' => 'cert.jpg'], $result['picture']);
    }

    #[Test]
    public function it_formats_certification_without_picture(): void
    {
        $certification = $this->createMockCertificationWithoutPicture();

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Juin 2023');

        $result = $this->formatter->formatCertification($certification);

        $this->assertNull($result['picture']);
        $this->assertNull($result['level']);
        $this->assertNull($result['score']);
        $this->assertNull($result['link']);
    }

    #[Test]
    public function it_formats_technology_experience(): void
    {
        $techExperience = $this->createMockTechnologyExperience();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('I have been using PHP for 10 years');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'php-icon.svg']);

        $result = $this->formatter->formatTechnologyExperience($techExperience);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals(1, $result['technologyId']);
        $this->assertEquals('PHP', $result['name']);
        $this->assertEquals('I have been using PHP for 10 years', $result['description']);
        $this->assertEquals(5, $result['creationCount']);
        $this->assertEquals(TechnologyType::LANGUAGE, $result['type']);
        $this->assertEquals('Langage', $result['typeLabel']);
        $this->assertEquals(['filename' => 'php-icon.svg'], $result['iconPicture']);
    }

    #[Test]
    public function it_formats_technology(): void
    {
        $technology = $this->createMockTechnology();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('PHP is a programming language');

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'php-icon.svg']);

        $result = $this->formatter->formatTechnology($technology);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('PHP', $result['name']);
        $this->assertEquals('PHP is a programming language', $result['description']);
        $this->assertEquals(TechnologyType::LANGUAGE, $result['type']);
        $this->assertEquals(5, $result['creationCount']);
        $this->assertEquals(['filename' => 'php-icon.svg'], $result['iconPicture']);
    }

    /**
     * Create a mock Experience for testing.
     */
    private function createMockExperience(): Experience&MockInterface
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $titleKey */
        $titleKey = Mockery::mock(TranslationKey::class)->makePartial();
        $titleKey->translations = $translations;

        /** @var TranslationKey&MockInterface $shortDescKey */
        $shortDescKey = Mockery::mock(TranslationKey::class)->makePartial();
        $shortDescKey->translations = $translations;

        /** @var TranslationKey&MockInterface $fullDescKey */
        $fullDescKey = Mockery::mock(TranslationKey::class)->makePartial();
        $fullDescKey->translations = $translations;

        /** @var Picture&MockInterface $logo */
        $logo = Mockery::mock(Picture::class)->makePartial();

        $technology = $this->createMockTechnology();

        /** @var Experience&MockInterface $experience */
        $experience = Mockery::mock(Experience::class)->makePartial();
        $experience->id = 1;
        $experience->organization_name = 'Acme Corp';
        $experience->slug = 'acme-corp';
        $experience->location = 'Paris';
        $experience->website_url = 'https://acme.com';
        $experience->type = ExperienceType::EMPLOI;
        $experience->started_at = Carbon::create(2022, 1, 15);
        $experience->ended_at = Carbon::create(2024, 12, 1);
        $experience->titleTranslationKey = $titleKey;
        $experience->shortDescriptionTranslationKey = $shortDescKey;
        $experience->fullDescriptionTranslationKey = $fullDescKey;
        $experience->logo = $logo;
        $experience->technologies = new Collection([$technology]);

        return $experience;
    }

    /**
     * Create a minimal mock Experience without optional fields.
     */
    private function createMockExperienceMinimal(): Experience&MockInterface
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $titleKey */
        $titleKey = Mockery::mock(TranslationKey::class)->makePartial();
        $titleKey->translations = $translations;

        /** @var TranslationKey&MockInterface $shortDescKey */
        $shortDescKey = Mockery::mock(TranslationKey::class)->makePartial();
        $shortDescKey->translations = $translations;

        /** @var TranslationKey&MockInterface $fullDescKey */
        $fullDescKey = Mockery::mock(TranslationKey::class)->makePartial();
        $fullDescKey->translations = $translations;

        /** @var Experience&MockInterface $experience */
        $experience = Mockery::mock(Experience::class)->makePartial();
        $experience->id = 1;
        $experience->organization_name = 'Test Corp';
        $experience->slug = 'test-corp';
        $experience->location = 'Remote';
        $experience->website_url = null;
        $experience->type = ExperienceType::FORMATION;
        $experience->started_at = Carbon::create(2022, 1, 1);
        $experience->ended_at = null;
        $experience->titleTranslationKey = $titleKey;
        $experience->shortDescriptionTranslationKey = $shortDescKey;
        $experience->fullDescriptionTranslationKey = $fullDescKey;
        $experience->logo = null;
        $experience->technologies = new Collection;

        return $experience;
    }

    /**
     * Create a mock Certification for testing.
     */
    private function createMockCertification(): Certification&MockInterface
    {
        /** @var Picture&MockInterface $picture */
        $picture = Mockery::mock(Picture::class)->makePartial();

        /** @var Certification&MockInterface $certification */
        $certification = Mockery::mock(Certification::class)->makePartial();
        $certification->id = 1;
        $certification->name = 'AWS Certified';
        $certification->level = 'Professional';
        $certification->score = '950/1000';
        $certification->date = '2023-03-15';
        $certification->link = 'https://aws.amazon.com/certification';
        $certification->picture = $picture;

        return $certification;
    }

    /**
     * Create a mock Certification without optional fields.
     */
    private function createMockCertificationWithoutPicture(): Certification&MockInterface
    {
        /** @var Certification&MockInterface $certification */
        $certification = Mockery::mock(Certification::class)->makePartial();
        $certification->id = 2;
        $certification->name = 'Basic Certification';
        $certification->level = null;
        $certification->score = null;
        $certification->date = '2023-06-01';
        $certification->link = null;
        $certification->picture = null;

        return $certification;
    }

    /**
     * Create a mock TechnologyExperience for testing.
     */
    private function createMockTechnologyExperience(): TechnologyExperience&MockInterface
    {
        $technology = $this->createMockTechnology();

        $translations = new Collection;

        /** @var TranslationKey&MockInterface $descKey */
        $descKey = Mockery::mock(TranslationKey::class)->makePartial();
        $descKey->translations = $translations;

        /** @var TechnologyExperience&MockInterface $techExperience */
        $techExperience = Mockery::mock(TechnologyExperience::class)->makePartial();
        $techExperience->id = 1;
        $techExperience->technology = $technology;
        $techExperience->descriptionTranslationKey = $descKey;

        return $techExperience;
    }

    /**
     * Create a mock Technology for testing.
     */
    private function createMockTechnology(): Technology&MockInterface
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $descriptionKey */
        $descriptionKey = Mockery::mock(TranslationKey::class)->makePartial();
        $descriptionKey->translations = $translations;

        /** @var Picture&MockInterface $iconPicture */
        $iconPicture = Mockery::mock(Picture::class)->makePartial();

        /** @var Technology&MockInterface $technology */
        $technology = Mockery::mock(Technology::class)->makePartial();
        $technology->id = 1;
        $technology->name = 'PHP';
        $technology->type = TechnologyType::LANGUAGE;
        $technology->descriptionTranslationKey = $descriptionKey;
        $technology->iconPicture = $iconPicture;

        return $technology;
    }
}
