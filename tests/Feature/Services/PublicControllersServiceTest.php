<?php

namespace Tests\Feature\Services;

use App\Enums\BlogPostType;
use App\Enums\ExperienceType;
use App\Enums\GameReviewRating;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\BlogCategory;
use App\Models\BlogContentGallery;
use App\Models\BlogContentMarkdown;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\GameReview;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Services\PublicControllersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PublicControllersService::class)]
class PublicControllersServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_get_creation_count_by_technology(): void
    {
        Creation::factory()->withTechnologies(5)->count(3)->create();

        $service = new PublicControllersService;
        $result = $service->calcCreationCountByTechnology();

        $this->assertCount(15, $result);
    }

    #[Test]
    public function test_get_development_stats(): void
    {
        Creation::factory()->create([
            'started_at' => now()->subYears(2),
            'type' => 'tool',
        ]);

        Experience::factory()->create([
            'started_at' => now()->subYears(2),
            'type' => ExperienceType::EMPLOI,
        ]);

        $service = new PublicControllersService;
        $result = $service->getDevelopmentStats();

        $this->assertArrayHasKey('yearsOfExperience', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(2, $result['yearsOfExperience']);
        $this->assertEquals(1, $result['count']);
    }

    #[Test]
    public function test_get_laravel_creations(): void
    {
        $laravelTech = Technology::factory()->create([
            'name' => 'Laravel',
        ]);

        $laravelCreations = Creation::factory()->count(3)->create([
            'type' => 'website',
        ]);

        foreach ($laravelCreations as $creation) {
            $creation->technologies()->attach($laravelTech);
        }

        Creation::factory()->count(2)->create([
            'type' => 'website',
        ]);

        $service = new PublicControllersService;
        $result = $service->getLaravelCreations();

        $this->assertCount(3, $result);
        $this->assertEquals('Laravel', $result[0]['technologies'][0]['name']);
    }

    #[Test]
    public function test_get_laravel_creations_but_laravel_tech_doesnt_exists(): void
    {
        Creation::factory()->count(3)->create([
            'type' => 'website',
        ]);

        $service = new PublicControllersService;
        $result = $service->getLaravelCreations();

        $this->assertCount(0, $result);
    }

    #[Test]
    public function test_get_creations(): void
    {
        Creation::factory()->withTechnologies()->count(3)->create();

        $service = new PublicControllersService;
        $result = $service->getCreations();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('technologies', $result[0]);
        $this->assertArrayHasKey('name', $result[0]['technologies'][0]);
    }

    #[Test]
    public function test_format_technology_for_ssr(): void
    {
        $technology = Technology::factory()->create([
            'name' => 'Laravel',
        ]);

        Creation::factory()->count(3)->afterCreating(function (Creation $creation) use ($technology) {
            $creation->technologies()->attach($technology);
        })->create();

        $service = new PublicControllersService;
        $result = $service->formatTechnologyForSSR($technology);

        $this->assertEquals($technology->id, $result['id']);
        $this->assertEquals(3, $result['creationCount']);
        $this->assertEquals($technology->name, $result['name']);
        $this->assertEquals($technology->type, $result['type']);
        $this->assertArrayHasKey('iconPicture', $result);
        $this->assertNotNull($result['iconPicture']);
    }

    #[Test]
    public function test_get_technology_experiences(): void
    {
        TechnologyExperience::factory()->count(3)->create();

        $service = new PublicControllersService;
        $result = $service->getTechnologyExperiences();

        $this->assertCount(3, $result);
    }

    #[Test]
    public function test_get_experiences(): void
    {
        Experience::factory()->count(3)
            ->withTechnologies()
            ->create();

        $service = new PublicControllersService;
        $result = $service->getExperiences();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('technologies', $result[0]);
        $this->assertArrayHasKey('name', $result[0]['technologies'][0]);
    }

    #[Test]
    public function test_format_date_with_string(): void
    {
        $service = new PublicControllersService;

        $date = '01/04/2025';
        $result = $service->formatDate($date);

        $this->assertEquals('Janvier 2025', $result);
        $this->assertNotEquals('01/04/2025', $result);
    }

    #[Test]
    public function test_format_date_with_carbon_object(): void
    {
        $service = new PublicControllersService;

        $date = now();
        $result = $service->formatDate($date);

        $this->assertEquals(ucfirst(now()->translatedFormat('F Y')), $result);
        $this->assertNotEquals(now(), $result);
    }

    #[Test]
    public function test_format_date_returns_null_if_date_is_null(): void
    {
        $service = new PublicControllersService;

        $result = $service->formatDate(null);

        $this->assertNull($result);
    }

    #[Test]
    public function test_format_creation_for_ssr_short(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'type' => 'website',
            'started_at' => now(),
            'ended_at' => now()->addMonth(),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRShort($creation);

        $this->assertEquals($creation->id, $result['id']);
        $this->assertEquals($creation->name, $result['name']);
        $this->assertEquals($creation->type, $result['type']);
        $this->assertEquals($creation->slug, $result['slug']);
        $this->assertEquals($creation->started_at, $result['startedAt']);
        $this->assertEquals($creation->ended_at, $result['endedAt']);
        $this->assertArrayHasKey('technologies', $result);
        $this->assertCount($creation->technologies->count(), $result['technologies']);

        foreach ($creation->technologies as $technology) {
            $resultTechnology = collect($result['technologies'])->firstWhere('id', $technology->id);

            $this->assertEquals($technology->id, $resultTechnology['id']);
            $this->assertEquals($technology->name, $resultTechnology['name']);
            $this->assertEquals($technology->type, $resultTechnology['type']);
        }

        $this->assertArrayHasKey('logo', $result);
        $this->assertEquals($creation->logo->filename, $result['logo']['filename']);

        $this->assertArrayHasKey('coverImage', $result);
        $this->assertEquals($creation->coverImage->filename, $result['coverImage']['filename']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full(): void
    {
        $creation = Creation::factory()
            ->withFeatures(3)
            ->withScreenshots(4)
            ->withPeople(2)
            ->withReadyVideos(2)
            ->withTranscodingVideos(2)
            ->create([
                'name' => 'Test Creation',
                'type' => 'website',
                'started_at' => now(),
                'ended_at' => now()->addMonth(),
                'external_url' => 'https://example.com',
                'source_code_url' => 'https://github.com/example/repo',
            ]);

        $this->assertCount(3, $creation->features);
        $this->assertCount(4, $creation->screenshots);
        $this->assertCount(4, $creation->videos);

        $featureWithoutPicture = $creation->features->first();
        $featureWithoutPicture->update(['picture_id' => null]);

        $screenshotWithoutCaption = $creation->screenshots->first();
        $screenshotWithoutCaption->update(['caption_translation_key_id' => null]);

        $creation->refresh();

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertEquals($creation->external_url, $result['externalUrl']);
        $this->assertEquals($creation->source_code_url, $result['sourceCodeUrl']);
        $this->assertCount($creation->features->count(), $result['features']);
        $this->assertCount($creation->screenshots->count(), $result['screenshots']);
        $this->assertCount(2, $result['videos']);

        foreach ($creation->features as $feature) {
            $resultFeature = collect($result['features'])->firstWhere('id', $feature->id);

            $this->assertEquals($feature->id, $resultFeature['id']);

            $currentLocale = app()->getLocale();
            $fallbackLocale = config('app.fallback_locale');

            $titleTranslation = $feature->titleTranslationKey->translations->firstWhere('locale', $currentLocale)
                ?? $feature->titleTranslationKey->translations->firstWhere('locale', $fallbackLocale);
            $descriptionTranslation = $feature->descriptionTranslationKey->translations->firstWhere('locale', $currentLocale)
                ?? $feature->descriptionTranslationKey->translations->firstWhere('locale', $fallbackLocale);

            $featureName = $titleTranslation ? $titleTranslation->text : '';
            $featureDescription = $descriptionTranslation ? $descriptionTranslation->text : '';

            $this->assertEquals($featureName, $resultFeature['title']);
            $this->assertEquals($featureDescription, $resultFeature['description']);

            if ($feature->picture_id) {
                $this->assertNotNull($resultFeature['picture']);
                $this->assertEquals($feature->picture->filename, $resultFeature['picture']['filename']);
                $this->assertArrayHasKey('avif', $resultFeature['picture']);
                $this->assertArrayHasKey('webp', $resultFeature['picture']);
                $this->assertArrayHasKey('jpg', $resultFeature['picture']);
            } else {
                $this->assertNull($resultFeature['picture']);
            }
        }

        foreach ($creation->screenshots as $screenshot) {
            $resultScreenshot = collect($result['screenshots'])->firstWhere('id', $screenshot->id);

            $this->assertEquals($screenshot->id, $resultScreenshot['id']);

            if ($screenshot->captionTranslationKey) {
                $currentLocale = app()->getLocale();
                $fallbackLocale = config('app.fallback_locale');

                $captionTranslation = $screenshot->captionTranslationKey->translations->firstWhere('locale', $currentLocale)
                    ?? $screenshot->captionTranslationKey->translations->firstWhere('locale', $fallbackLocale);
                $caption = $captionTranslation ? $captionTranslation->text : '';
                $this->assertEquals($caption, $resultScreenshot['caption']);
            } else {
                $this->assertEmpty($resultScreenshot['caption']);
            }

            $this->assertNotNull($resultScreenshot['picture']);
            $this->assertEquals($screenshot->picture->filename, $resultScreenshot['picture']['filename']);

            $this->assertArrayHasKey('avif', $resultScreenshot['picture']);
            $this->assertArrayHasKey('webp', $resultScreenshot['picture']);
            $this->assertArrayHasKey('jpg', $resultScreenshot['picture']);
        }

        foreach ($creation->people as $person) {
            $resultPerson = collect($result['people'])->firstWhere('id', $person->id);

            $this->assertEquals($person->id, $resultPerson['id']);
            $this->assertEquals($person->name, $resultPerson['name']);
            $this->assertEquals($person->url, $resultPerson['url']);
            $this->assertEquals($person->picture->filename, $resultPerson['picture']['filename']);
        }

        foreach ($creation->videos as $video) {
            if ($video->status == VideoStatus::READY && $video->visibility == VideoVisibility::PUBLIC) {
                $resultVideo = collect($result['videos'])->firstWhere('id', $video->id);

                $this->assertEquals($video->id, $resultVideo['id']);
                $this->assertEquals($video->bunny_video_id, $resultVideo['bunnyVideoId']);
                $this->assertEquals($video->name, $resultVideo['name']);
                $this->assertEquals($video->coverPicture->filename, $resultVideo['coverPicture']['filename']);
                $this->assertArrayHasKey('avif', $resultVideo['coverPicture']);
                $this->assertArrayHasKey('webp', $resultVideo['coverPicture']);
                $this->assertArrayHasKey('jpg', $resultVideo['coverPicture']);
            } else {
                $this->assertArrayNotHasKey($video->id, collect($result['videos']));
            }
        }
    }

    #[Test]
    public function test_translation_fallback_when_current_locale_translation_missing(): void
    {
        app()->setLocale('es'); // Set a locale that doesn't have translations
        config(['app.fallback_locale' => 'en']);

        $technology = Technology::factory()->create();

        // Create only English translation, no Spanish
        $technology->descriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $technology->descriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English description',
        ]);

        $service = new PublicControllersService;
        $result = $service->formatTechnologyForSSR($technology);

        $this->assertEquals('English description', $result['description']);
    }

    #[Test]
    public function test_translation_uses_current_locale_when_available(): void
    {
        app()->setLocale('fr');
        config(['app.fallback_locale' => 'en']);

        $technology = Technology::factory()->create();

        // Create both French and English translations
        $technology->descriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $technology->descriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English description',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $technology->descriptionTranslationKey->id,
            'locale' => 'fr',
            'text' => 'Description française',
        ]);

        $service = new PublicControllersService;
        $result = $service->formatTechnologyForSSR($technology);

        $this->assertEquals('Description française', $result['description']);
    }

    #[Test]
    public function test_translation_returns_empty_when_no_translation_available(): void
    {
        app()->setLocale('es');
        config(['app.fallback_locale' => 'en']);

        $technology = Technology::factory()->create();

        // Remove all translations
        $technology->descriptionTranslationKey->translations()->delete();

        $service = new PublicControllersService;
        $result = $service->formatTechnologyForSSR($technology);

        $this->assertEquals('', $result['description']);
    }

    #[Test]
    public function test_creation_translation_fallback_for_short_description(): void
    {
        app()->setLocale('es');
        config(['app.fallback_locale' => 'en']);

        $creation = Creation::factory()->create();

        // Create only English translation for short description
        $creation->shortDescriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $creation->shortDescriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English short description',
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRShort($creation);

        $this->assertEquals('English short description', $result['shortDescription']);
    }

    #[Test]
    public function test_creation_translation_fallback_for_full_description(): void
    {
        app()->setLocale('es');
        config(['app.fallback_locale' => 'en']);

        $creation = Creation::factory()->create();

        // Create only English translation for full description
        $creation->fullDescriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $creation->fullDescriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English full description',
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertEquals('English full description', $result['fullDescription']);
    }

    #[Test]
    public function test_experience_translation_fallback(): void
    {
        app()->setLocale('es');
        config(['app.fallback_locale' => 'en']);

        $experience = Experience::factory()->create();

        // Create only English translations
        $experience->titleTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $experience->titleTranslationKey->id,
            'locale' => 'en',
            'text' => 'English title',
        ]);

        $experience->shortDescriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $experience->shortDescriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English short description',
        ]);

        $experience->fullDescriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $experience->fullDescriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English full description',
        ]);

        $service = new PublicControllersService;
        $result = $service->getExperiences();

        $this->assertEquals('English title', $result[0]['title']);
        $this->assertEquals('English short description', $result[0]['shortDescription']);
        $this->assertEquals('English full description', $result[0]['fullDescription']);
    }

    #[Test]
    public function test_technology_experience_translation_fallback(): void
    {
        app()->setLocale('es');
        config(['app.fallback_locale' => 'en']);

        $techExperience = TechnologyExperience::factory()->create();

        // Create only English translation
        $techExperience->descriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $techExperience->descriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English tech experience description',
        ]);

        $service = new PublicControllersService;
        $result = $service->getTechnologyExperiences();

        $this->assertEquals('English tech experience description', $result[0]['description']);
    }

    #[Test]
    public function test_format_creation_with_github_data(): void
    {
        $creation = Creation::factory()
            ->withFeatures(1)
            ->withScreenshots(1)
            ->create([
                'name' => 'Test Creation',
                'source_code_url' => 'https://github.com/owner/repo',
            ]);

        $mockGitHubResponse = [
            'name' => 'repo',
            'description' => 'Test repository description',
            'stargazers_count' => 150,
            'forks_count' => 25,
            'watchers_count' => 80,
            'language' => 'PHP',
            'topics' => ['laravel', 'php', 'web'],
            'license' => ['name' => 'MIT License'],
            'updated_at' => '2024-01-15T10:30:00Z',
            'created_at' => '2023-06-01T08:00:00Z',
            'open_issues_count' => 5,
            'default_branch' => 'main',
            'size' => 2048,
            'html_url' => 'https://github.com/owner/repo',
            'homepage' => 'https://example.com',
        ];

        $mockLanguagesResponse = [
            'PHP' => 60000,
            'JavaScript' => 25000,
            'Vue' => 10000,
            'CSS' => 5000,
        ];

        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response($mockGitHubResponse, 200),
            'api.github.com/repos/owner/repo/languages' => Http::response($mockLanguagesResponse, 200),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('githubLanguages', $result);

        $this->assertNotNull($result['githubData']);
        $this->assertEquals('repo', $result['githubData']['name']);
        $this->assertEquals('Test repository description', $result['githubData']['description']);
        $this->assertEquals(150, $result['githubData']['stars']);
        $this->assertEquals(25, $result['githubData']['forks']);
        $this->assertEquals(80, $result['githubData']['watchers']);
        $this->assertEquals('PHP', $result['githubData']['language']);
        $this->assertEquals(['laravel', 'php', 'web'], $result['githubData']['topics']);
        $this->assertEquals('MIT License', $result['githubData']['license']);
        $this->assertEquals(5, $result['githubData']['open_issues']);
        $this->assertEquals('main', $result['githubData']['default_branch']);
        $this->assertEquals(2048, $result['githubData']['size']);
        $this->assertEquals('https://github.com/owner/repo', $result['githubData']['url']);
        $this->assertEquals('https://example.com', $result['githubData']['homepage']);

        $this->assertNotNull($result['githubLanguages']);
        $this->assertEquals(60.0, $result['githubLanguages']['PHP']);
        $this->assertEquals(25.0, $result['githubLanguages']['JavaScript']);
        $this->assertEquals(10.0, $result['githubLanguages']['Vue']);
        $this->assertEquals(5.0, $result['githubLanguages']['CSS']);
    }

    #[Test]
    public function test_format_creation_without_github_url(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'source_code_url' => null,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('githubLanguages', $result);
        $this->assertNull($result['githubData']);
        $this->assertNull($result['githubLanguages']);
    }

    #[Test]
    public function test_format_creation_with_non_github_url(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'source_code_url' => 'https://gitlab.com/owner/repo',
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('githubLanguages', $result);
        $this->assertNull($result['githubData']);
        $this->assertNull($result['githubLanguages']);
    }

    #[Test]
    public function test_format_creation_with_github_api_error(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'source_code_url' => 'https://github.com/owner/nonexistent',
        ]);

        Http::fake([
            'api.github.com/repos/owner/nonexistent' => Http::response(null, 404),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('githubLanguages', $result);
        $this->assertNull($result['githubData']);
        $this->assertNull($result['githubLanguages']);
    }

    #[Test]
    public function test_format_creation_with_private_github_repo(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'source_code_url' => 'https://github.com/owner/private-repo',
        ]);

        Http::fake([
            'api.github.com/repos/owner/private-repo' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('githubLanguages', $result);
        $this->assertNull($result['githubData']);
        $this->assertNull($result['githubLanguages']);
    }

    #[Test]
    public function test_format_creation_with_github_rate_limit(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'source_code_url' => 'https://github.com/owner/repo',
        ]);

        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response([
                'message' => 'API rate limit exceeded',
            ], 403),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('githubLanguages', $result);
        $this->assertNull($result['githubData']);
        $this->assertNull($result['githubLanguages']);
    }

    #[Test]
    public function test_format_picture_for_ssr(): void
    {
        $picture = \App\Models\Picture::factory()->create([
            'filename' => 'test.jpg',
            'width' => 1920,
            'height' => 1080,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatPictureForSSR($picture);

        $this->assertEquals('test.jpg', $result['filename']);
        $this->assertEquals(1920, $result['width']);
        $this->assertEquals(1080, $result['height']);

        // Check all image format arrays
        $this->assertArrayHasKey('avif', $result);
        $this->assertArrayHasKey('webp', $result);
        $this->assertArrayHasKey('jpg', $result);

        // Check all sizes for each format
        $sizes = ['thumbnail', 'small', 'medium', 'large', 'full'];
        foreach (['avif', 'webp', 'jpg'] as $format) {
            foreach ($sizes as $size) {
                $this->assertArrayHasKey($size, $result[$format]);
                $this->assertIsString($result[$format][$size]);
            }
        }
    }

    #[Test]
    public function test_format_video_for_ssr(): void
    {
        $coverPicture = \App\Models\Picture::factory()->create();
        $video = \App\Models\Video::factory()->create([
            'bunny_video_id' => 'test-video-123',
            'name' => 'Test Video',
            'cover_picture_id' => $coverPicture->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatVideoForSSR($video);

        $this->assertEquals($video->id, $result['id']);
        $this->assertEquals('test-video-123', $result['bunnyVideoId']);
        $this->assertEquals('Test Video', $result['name']);
        $this->assertArrayHasKey('coverPicture', $result);
        $this->assertEquals($coverPicture->filename, $result['coverPicture']['filename']);
        $this->assertArrayHasKey('libraryId', $result);
        $this->assertEquals(config('services.bunny.stream_library_id'), $result['libraryId']);
    }

    #[Test]
    public function test_get_certifications(): void
    {
        $certifications = \App\Models\Certification::factory()->count(3)->create();

        // Sort by date desc as the service does
        $certifications = $certifications->sortByDesc('date')->values();

        $service = new PublicControllersService;
        $result = $service->getCertifications();

        $this->assertCount(3, $result);

        // Check they are sorted by date desc
        $previousDate = null;
        foreach ($result as $cert) {
            if ($previousDate !== null) {
                $this->assertLessThanOrEqual($previousDate, $cert['date']);
            }
            $previousDate = $cert['date'];

            $this->assertArrayHasKey('id', $cert);
            $this->assertArrayHasKey('name', $cert);
            $this->assertArrayHasKey('level', $cert);
            $this->assertArrayHasKey('score', $cert);
            $this->assertArrayHasKey('date', $cert);
            $this->assertArrayHasKey('dateFormatted', $cert);
            $this->assertArrayHasKey('link', $cert);
            $this->assertArrayHasKey('picture', $cert);
        }
    }

    #[Test]
    public function test_get_experiences_by_type_emploi(): void
    {
        $emploi = Experience::factory()->count(2)->create([
            'type' => ExperienceType::EMPLOI,
        ]);

        $formation = Experience::factory()->count(3)->create([
            'type' => ExperienceType::FORMATION,
        ]);

        $service = new PublicControllersService;
        $result = $service->getExperiencesByType(ExperienceType::EMPLOI);

        $this->assertCount(2, $result);

        foreach ($result as $exp) {
            $this->assertEquals(ExperienceType::EMPLOI, $exp['type']);
        }
    }

    #[Test]
    public function test_get_experiences_by_type_formation(): void
    {
        $emploi = Experience::factory()->count(2)->create([
            'type' => ExperienceType::EMPLOI,
        ]);

        $formation = Experience::factory()->count(3)->create([
            'type' => ExperienceType::FORMATION,
        ]);

        $service = new PublicControllersService;
        $result = $service->getExperiencesByType(ExperienceType::FORMATION);

        $this->assertCount(3, $result);

        foreach ($result as $exp) {
            $this->assertEquals(ExperienceType::FORMATION, $exp['type']);
        }
    }

    #[Test]
    public function test_get_certifications_career_data(): void
    {
        $certifications = \App\Models\Certification::factory()->count(2)->create();
        $workExperiences = Experience::factory()->count(3)->create([
            'type' => ExperienceType::EMPLOI,
        ]);
        $educationExperiences = Experience::factory()->count(2)->create([
            'type' => ExperienceType::FORMATION,
        ]);

        $service = new PublicControllersService;
        $result = $service->getCertificationsCareerData();

        $this->assertArrayHasKey('certifications', $result);
        $this->assertArrayHasKey('educationExperiences', $result);
        $this->assertArrayHasKey('workExperiences', $result);

        $this->assertCount(2, $result['certifications']);
        $this->assertCount(2, $result['educationExperiences']);
        $this->assertCount(3, $result['workExperiences']);
    }

    #[Test]
    public function test_format_certification_for_ssr_with_picture(): void
    {
        $picture = \App\Models\Picture::factory()->create();
        $certification = \App\Models\Certification::factory()->create([
            'name' => 'AWS Certified',
            'level' => 'Professional',
            'score' => '850/1000',
            'date' => '2024-01-15',
            'link' => 'https://aws.amazon.com/verify',
            'picture_id' => $picture->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCertificationForSSR($certification);

        $this->assertEquals($certification->id, $result['id']);
        $this->assertEquals('AWS Certified', $result['name']);
        $this->assertEquals('Professional', $result['level']);
        $this->assertEquals('850/1000', $result['score']);
        $this->assertEquals('2024-01-15', $result['date']);
        $this->assertNotEmpty($result['dateFormatted']);
        $this->assertEquals('https://aws.amazon.com/verify', $result['link']);
        $this->assertNotNull($result['picture']);
        $this->assertEquals($picture->filename, $result['picture']['filename']);
    }

    #[Test]
    public function test_format_certification_for_ssr_without_picture(): void
    {
        $certification = \App\Models\Certification::factory()->create([
            'picture_id' => null,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCertificationForSSR($certification);

        $this->assertNull($result['picture']);
    }

    #[Test]
    public function test_format_experience_for_ssr_with_logo(): void
    {
        $logo = \App\Models\Picture::factory()->create();
        $experience = Experience::factory()->withTechnologies(2)->create([
            'logo_id' => $logo->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatExperienceForSSR($experience);

        $this->assertEquals($experience->id, $result['id']);
        $this->assertNotNull($result['logo']);
        $this->assertEquals($logo->filename, $result['logo']['filename']);
        $this->assertCount(2, $result['technologies']);
        $this->assertEquals($experience->organization_name, $result['organizationName']);
        $this->assertEquals($experience->slug, $result['slug']);
        $this->assertEquals($experience->location, $result['location']);
        $this->assertEquals($experience->website_url, $result['websiteUrl']);
        $this->assertEquals($experience->type, $result['type']);
        $this->assertEquals($experience->started_at->toDateString(), $result['startedAt']);
        $this->assertNotEmpty($result['startedAtFormatted']);
    }

    #[Test]
    public function test_format_experience_for_ssr_with_logo_factory_default(): void
    {
        // Experience factory includes a logo by default
        $experience = Experience::factory()->create();

        $service = new PublicControllersService;
        $result = $service->formatExperienceForSSR($experience);

        // Factory creates logo by default, so it should not be null
        $this->assertNotNull($result['logo']);
        $this->assertEquals($experience->logo->filename, $result['logo']['filename']);
    }

    #[Test]
    public function test_format_experience_for_ssr_with_ended_at(): void
    {
        $experience = Experience::factory()->create([
            'ended_at' => now(),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatExperienceForSSR($experience);

        $this->assertNotNull($result['endedAt']);
        $this->assertNotNull($result['endedAtFormatted']);
    }

    #[Test]
    public function test_format_experience_for_ssr_without_ended_at(): void
    {
        $experience = Experience::factory()->create([
            'ended_at' => null,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatExperienceForSSR($experience);

        $this->assertNull($result['endedAt']);
        $this->assertNull($result['endedAtFormatted']);
    }

    #[Test]
    public function test_get_blog_posts_for_public_home(): void
    {
        $category = BlogCategory::factory()->create();
        $posts = BlogPost::factory()->count(3)->create([
            'category_id' => $category->id,
            'created_at' => now()->subDays(1),
        ]);

        // Create markdown content for each post
        foreach ($posts as $post) {
            $markdown = BlogContentMarkdown::factory()->create();
            BlogPostContent::factory()->create([
                'blog_post_id' => $post->id,
                'content_type' => BlogContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => 1,
            ]);
        }

        $service = new PublicControllersService;
        $result = $service->getBlogPostsForPublicHome();

        $this->assertCount(3, $result);

        // Verify posts are ordered by created_at desc
        $previousDate = null;
        foreach ($result as $post) {
            if ($previousDate !== null) {
                $this->assertLessThanOrEqual($previousDate, $post->created_at);
            }
            $previousDate = $post->created_at;
        }
    }

    #[Test]
    public function test_format_blog_post_for_ssr_short(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'category_id' => $category->id,
        ]);

        // Create markdown content with text longer than 150 chars
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => str_repeat('This is a test content. ', 20),
        ]);

        $markdown = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatBlogPostForSSRShort($post);

        $this->assertEquals($post->id, $result['id']);
        $this->assertEquals($post->slug, $result['slug']);
        $this->assertEquals($post->type, $result['type']);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('coverImage', $result);
        $this->assertArrayHasKey('excerpt', $result);
        $this->assertLessThanOrEqual(153, strlen($result['excerpt'])); // 150 + '...'
    }

    #[Test]
    public function test_format_blog_post_for_ssr_hero(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'category_id' => $category->id,
        ]);

        // Create markdown content with text longer than 300 chars
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => str_repeat('This is a test content for hero section. ', 20),
        ]);

        $markdown = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatBlogPostForSSRHero($post);

        $this->assertEquals($post->id, $result['id']);
        $this->assertArrayHasKey('excerpt', $result);
        $this->assertLessThanOrEqual(303, strlen($result['excerpt'])); // 300 + '...'
    }

    #[Test]
    public function test_format_blog_post_with_no_markdown_content(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'category_id' => $category->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatBlogPostForSSRShort($post);

        $this->assertEquals('', $result['excerpt']);
    }

    #[Test]
    public function test_get_blog_posts_for_index_with_category_filter(): void
    {
        $category1 = BlogCategory::factory()->create(['slug' => 'tech']);
        $category2 = BlogCategory::factory()->create(['slug' => 'gaming']);

        $techPosts = BlogPost::factory()->count(3)->create([
            'category_id' => $category1->id,
        ]);

        $gamingPosts = BlogPost::factory()->count(2)->create([
            'category_id' => $category2->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostsForIndex(['category' => 'tech'], 10);

        $this->assertEquals(3, $result['total']);
        $this->assertCount(3, $result['data']);
    }

    #[Test]
    public function test_get_blog_posts_for_index_with_search_filter(): void
    {
        $category = BlogCategory::factory()->create();

        $post1TitleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $post1TitleKey->id,
            'locale' => 'en',
            'text' => 'Laravel Tips and Tricks',
        ]);

        $post1 = BlogPost::factory()->create([
            'category_id' => $category->id,
            'title_translation_key_id' => $post1TitleKey->id,
        ]);

        $post2TitleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $post2TitleKey->id,
            'locale' => 'en',
            'text' => 'Vue.js Best Practices',
        ]);

        $post2 = BlogPost::factory()->create([
            'category_id' => $category->id,
            'title_translation_key_id' => $post2TitleKey->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostsForIndex(['search' => 'Laravel'], 10);

        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function test_get_blog_posts_for_index_with_oldest_sort(): void
    {
        $category = BlogCategory::factory()->create();

        $oldPost = BlogPost::factory()->create([
            'category_id' => $category->id,
            'created_at' => now()->subDays(10),
        ]);

        $newPost = BlogPost::factory()->create([
            'category_id' => $category->id,
            'created_at' => now()->subDays(1),
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostsForIndex(['sort' => 'oldest'], 10);

        $this->assertEquals($oldPost->id, $result['data'][0]['id']);
    }

    #[Test]
    public function test_get_blog_posts_for_index_with_alphabetical_sort(): void
    {
        $category = BlogCategory::factory()->create();

        $postBTitleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $postBTitleKey->id,
            'locale' => 'en',
            'text' => 'Beta Post',
        ]);

        $postB = BlogPost::factory()->create([
            'category_id' => $category->id,
            'title_translation_key_id' => $postBTitleKey->id,
        ]);

        $postATitleKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $postATitleKey->id,
            'locale' => 'en',
            'text' => 'Alpha Post',
        ]);

        $postA = BlogPost::factory()->create([
            'category_id' => $category->id,
            'title_translation_key_id' => $postATitleKey->id,
        ]);

        app()->setLocale('en');
        $service = new PublicControllersService;
        $result = $service->getBlogPostsForIndex(['sort' => 'alphabetical'], 10);

        $this->assertEquals($postA->id, $result['data'][0]['id']);
    }

    #[Test]
    public function test_get_blog_posts_for_index_with_pagination(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPost::factory()->count(25)->create([
            'category_id' => $category->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostsForIndex([], 10);

        $this->assertEquals(10, $result['per_page']);
        $this->assertEquals(3, $result['last_page']);
        $this->assertEquals(25, $result['total']);
        $this->assertCount(10, $result['data']);
    }

    #[Test]
    public function test_get_blog_categories(): void
    {
        $categories = BlogCategory::factory()->count(3)->create();

        $service = new PublicControllersService;
        $result = $service->getBlogCategories();

        $this->assertCount(3, $result);
        foreach ($result as $cat) {
            $this->assertArrayHasKey('id', $cat);
            $this->assertArrayHasKey('name', $cat);
            $this->assertArrayHasKey('slug', $cat);
            $this->assertArrayHasKey('color', $cat);
        }
    }

    #[Test]
    public function test_get_blog_categories_with_counts(): void
    {
        $category1 = BlogCategory::factory()->create();
        $category2 = BlogCategory::factory()->create();

        BlogPost::factory()->count(5)->create([
            'category_id' => $category1->id,
        ]);

        BlogPost::factory()->count(3)->create([
            'category_id' => $category2->id,
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogCategoriesWithCounts();

        $this->assertCount(2, $result);

        $cat1 = collect($result)->firstWhere('id', $category1->id);
        $cat2 = collect($result)->firstWhere('id', $category2->id);

        $this->assertEquals(5, $cat1['postCount']);
        $this->assertEquals(3, $cat2['postCount']);
    }

    #[Test]
    public function test_get_blog_post_by_slug(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'slug' => 'test-post',
            'category_id' => $category->id,
            'type' => BlogPostType::ARTICLE,
        ]);

        // Add markdown content
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => 'This is the blog content.',
        ]);

        $markdown = BlogContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostBySlug('test-post');

        $this->assertNotNull($result);
        $this->assertEquals($post->id, $result['id']);
        $this->assertEquals('test-post', $result['slug']);
        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals('This is the blog content.', $result['contents'][0]['markdown']);
    }

    #[Test]
    public function test_get_blog_post_by_slug_not_found(): void
    {
        $service = new PublicControllersService;
        $result = $service->getBlogPostBySlug('non-existent-slug');

        $this->assertNull($result);
    }

    #[Test]
    public function test_get_blog_post_by_slug_with_gallery(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'slug' => 'test-post-gallery',
            'category_id' => $category->id,
        ]);

        // Add gallery content
        $gallery = BlogContentGallery::factory()->create();
        $pictures = \App\Models\Picture::factory()->count(3)->create();

        foreach ($pictures as $index => $picture) {
            // Create caption for first picture only
            if ($index === 0) {
                $captionKey = TranslationKey::factory()->create();
                Translation::factory()->create([
                    'translation_key_id' => $captionKey->id,
                    'locale' => 'en',
                    'text' => 'Gallery caption',
                ]);
                $gallery->pictures()->attach($picture->id, [
                    'order' => $index,
                    'caption_translation_key_id' => $captionKey->id,
                ]);
            } else {
                $gallery->pictures()->attach($picture->id, [
                    'order' => $index,
                ]);
            }
        }

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostBySlug('test-post-gallery');

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals(BlogContentGallery::class, $result['contents'][0]['content_type']);
        $this->assertArrayHasKey('gallery', $result['contents'][0]);
        $this->assertCount(3, $result['contents'][0]['gallery']['pictures']);
        $this->assertEquals('Gallery caption', $result['contents'][0]['gallery']['pictures'][0]['caption']);
    }

    #[Test]
    public function test_get_blog_post_by_slug_with_game_review(): void
    {
        $category = BlogCategory::factory()->create();

        // Create blog post first
        $post = BlogPost::factory()->create([
            'slug' => 'test-game-review',
            'category_id' => $category->id,
            'type' => BlogPostType::GAME_REVIEW,
        ]);

        // Then create game review linked to the blog post
        $gameReview = GameReview::factory()->create([
            'blog_post_id' => $post->id,
            'game_title' => 'Test Game',
            'rating' => GameReviewRating::POSITIVE,
            'platforms' => ['PC', 'PS5', 'Xbox'],
        ]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostBySlug('test-game-review');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('gameReview', $result);
        $this->assertEquals('Test Game', $result['gameReview']['gameTitle']);
        $this->assertEquals(GameReviewRating::POSITIVE, $result['gameReview']['rating']);
        $this->assertEquals(['PC', 'PS5', 'Xbox'], $result['gameReview']['platforms']);
    }

    #[Test]
    public function test_format_creation_with_packagist_url(): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Package',
            'external_url' => 'https://packagist.org/packages/vendor/package',
        ]);

        Http::fake([
            'packagist.org/packages/vendor/package.json' => Http::response([
                'package' => [
                    'name' => 'vendor/package',
                    'description' => 'Test package description',
                    'downloads' => [
                        'total' => 50000,
                        'daily' => 150,
                        'monthly' => 4500,
                    ],
                    'favers' => 250,
                    'dependents' => 10,
                    'suggesters' => 2,
                    'versions' => [
                        'dev-main' => [
                            'version' => 'dev-main',
                            'time' => '2024-01-15T10:00:00+00:00',
                            'license' => ['MIT'],
                            'require' => [
                                'php' => '^8.0',
                                'laravel/framework' => '^9.0|^10.0',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('packagistData', $result);
        $this->assertNotNull($result['packagistData']);
        $this->assertEquals('vendor/package', $result['packagistData']['name']);
        $this->assertEquals('Test package description', $result['packagistData']['description']);
        $this->assertEquals(50000, $result['packagistData']['downloads']);
    }

    #[Test]
    public function test_format_creation_with_packagist_api_error(): void
    {
        $creation = Creation::factory()->create([
            'external_url' => 'https://packagist.org/packages/vendor/nonexistent',
        ]);

        Http::fake([
            'packagist.org/packages/vendor/nonexistent.json' => Http::response(null, 404),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('packagistData', $result);
        $this->assertNull($result['packagistData']);
    }

    #[Test]
    public function test_format_creation_without_packagist_url(): void
    {
        $creation = Creation::factory()->create([
            'external_url' => 'https://example.com',
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('packagistData', $result);
        $this->assertNull($result['packagistData']);
    }

    #[Test]
    public function test_format_creation_with_person_without_picture(): void
    {
        $person = \App\Models\Person::factory()->create([
            'name' => 'John Doe',
            'url' => 'https://johndoe.com',
            'picture_id' => null,
        ]);

        $creation = Creation::factory()->create();
        $creation->people()->attach($person);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertCount(1, $result['people']);
        $this->assertEquals('John Doe', $result['people'][0]['name']);
        $this->assertEquals('https://johndoe.com', $result['people'][0]['url']);
        $this->assertNull($result['people'][0]['picture']);
    }

    #[Test]
    public function test_get_development_stats_without_experience(): void
    {
        Creation::factory()->count(3)->create(['type' => 'website']);

        $service = new PublicControllersService;
        $result = $service->getDevelopmentStats();

        $this->assertEquals(3, $result['count']);
        $this->assertEquals(0, $result['yearsOfExperience']);
    }

    #[Test]
    public function test_get_blog_posts_for_index_with_multiple_category_filters(): void
    {
        $category1 = BlogCategory::factory()->create(['slug' => 'tech']);
        $category2 = BlogCategory::factory()->create(['slug' => 'gaming']);
        $category3 = BlogCategory::factory()->create(['slug' => 'news']);

        BlogPost::factory()->count(2)->create(['category_id' => $category1->id]);
        BlogPost::factory()->count(3)->create(['category_id' => $category2->id]);
        BlogPost::factory()->count(1)->create(['category_id' => $category3->id]);

        $service = new PublicControllersService;
        $result = $service->getBlogPostsForIndex(['category' => ['tech', 'gaming']], 10);

        $this->assertEquals(5, $result['total']);
    }

    #[Test]
    public function test_format_blog_post_short_with_markdown_special_chars(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create(['category_id' => $category->id]);

        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => '# Header **bold** _italic_ `code` text',
        ]);

        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $markdownKey->id]);
        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatBlogPostForSSRShort($post);

        $this->assertStringNotContainsString('#', $result['excerpt']);
        $this->assertStringNotContainsString('*', $result['excerpt']);
        $this->assertStringNotContainsString('_', $result['excerpt']);
        $this->assertStringNotContainsString('`', $result['excerpt']);
    }

    #[Test]
    public function test_translation_fallback_when_locale_same_as_fallback(): void
    {
        app()->setLocale('en');
        config(['app.fallback_locale' => 'en']);

        $technology = Technology::factory()->create();
        $technology->descriptionTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $technology->descriptionTranslationKey->id,
            'locale' => 'en',
            'text' => 'English description',
        ]);

        $service = new PublicControllersService;
        $result = $service->formatTechnologyForSSR($technology);

        $this->assertEquals('English description', $result['description']);
    }

    #[Test]
    public function test_format_creation_with_github_data_null_but_url_exists(): void
    {
        $creation = Creation::factory()->create([
            'source_code_url' => 'https://github.com/owner/repo',
        ]);

        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response(null, 500),
        ]);

        $service = new PublicControllersService;
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('githubLanguages', $result);
        $this->assertNull($result['githubData']);
        $this->assertNull($result['githubLanguages']);
    }

    #[Test]
    public function test_format_blog_post_excerpt_with_no_space_in_truncation(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create(['category_id' => $category->id]);

        // Create markdown content with a very long word that exceeds 150 chars without spaces
        $longWord = str_repeat('a', 160); // 160 chars without any space
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => $longWord,
        ]);

        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $markdownKey->id]);
        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatBlogPostForSSRShort($post);

        // When no space is found, it should truncate at maxLength
        $this->assertEquals(str_repeat('a', 150).'...', $result['excerpt']);
    }

    #[Test]
    public function test_format_blog_post_excerpt_with_empty_markdown_translation(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create(['category_id' => $category->id]);

        // Create markdown content with empty text
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => '',
        ]);

        $markdown = BlogContentMarkdown::factory()->create(['translation_key_id' => $markdownKey->id]);
        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => BlogContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService;
        $result = $service->formatBlogPostForSSRShort($post);

        $this->assertEquals('', $result['excerpt']);
    }
}
