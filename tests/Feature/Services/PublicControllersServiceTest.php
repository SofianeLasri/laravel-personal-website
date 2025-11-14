<?php

namespace Tests\Feature\Services;

use App\Enums\BlogPostType;
use App\Enums\ExperienceType;
use App\Enums\GameReviewRating;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostContent;
use App\Models\BlogPostDraft;
use App\Models\BlogPostDraftContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Creation;
use App\Models\Experience;
use App\Models\GameReview;
use App\Models\GameReviewDraft;
use App\Models\Technology;
use App\Models\TechnologyExperience;
use App\Models\Translation;
use App\Models\TranslationKey;
use App\Services\CustomEmojiResolverService;
use App\Services\PublicControllersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getLaravelCreations();

        $this->assertCount(0, $result);
    }

    #[Test]
    public function test_get_creations(): void
    {
        Creation::factory()->withTechnologies()->count(3)->create();

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getTechnologyExperiences();

        $this->assertCount(3, $result);
    }

    #[Test]
    public function test_get_experiences(): void
    {
        Experience::factory()->count(3)
            ->withTechnologies()
            ->create();

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getExperiences();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('technologies', $result[0]);
        $this->assertArrayHasKey('name', $result[0]['technologies'][0]);
    }

    /**
     * Data provider for date formatting scenarios
     */
    public static function dateFormattingProvider(): array
    {
        return [
            'string date' => [
                '01/04/2025',
                'Janvier 2025',
            ],
            'carbon object' => [
                fn () => now(),
                fn () => ucfirst(now()->translatedFormat('F Y')),
            ],
            'null date' => [
                null,
                null,
            ],
        ];
    }

    #[Test]
    #[DataProvider('dateFormattingProvider')]
    public function test_date_formatting($input, $expected): void
    {
        // Resolve callables
        $input = is_callable($input) ? $input() : $input;
        $expected = is_callable($expected) ? $expected() : $expected;

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatDate($input);

        $this->assertEquals($expected, $result);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

    /**
     * Data provider for technology translation fallback scenarios
     */
    public static function technologyTranslationProvider(): array
    {
        return [
            'fallback to English when Spanish missing' => [
                'es',
                'en',
                [
                    ['locale' => 'en', 'text' => 'English description'],
                ],
                'English description',
            ],
            'uses current locale when available' => [
                'fr',
                'en',
                [
                    ['locale' => 'en', 'text' => 'English description'],
                    ['locale' => 'fr', 'text' => 'Description française'],
                ],
                'Description française',
            ],
            'returns empty when no translation available' => [
                'es',
                'en',
                [],
                '',
            ],
            'locale same as fallback' => [
                'en',
                'en',
                [
                    ['locale' => 'en', 'text' => 'English description'],
                ],
                'English description',
            ],
        ];
    }

    #[Test]
    #[DataProvider('technologyTranslationProvider')]
    public function test_technology_translation_fallback(string $locale, string $fallbackLocale, array $translations, string $expected): void
    {
        app()->setLocale($locale);
        config(['app.fallback_locale' => $fallbackLocale]);

        $technology = Technology::factory()->create();

        // Remove existing translations and add test translations
        $technology->descriptionTranslationKey->translations()->delete();
        foreach ($translations as $translationData) {
            Translation::factory()->create([
                'translation_key_id' => $technology->descriptionTranslationKey->id,
                'locale' => $translationData['locale'],
                'text' => $translationData['text'],
            ]);
        }

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatTechnologyForSSR($technology);

        $this->assertEquals($expected, $result['description']);
    }

    /**
     * Data provider for creation translation fallback scenarios
     */
    public static function creationTranslationProvider(): array
    {
        return [
            'short description fallback' => [
                'shortDescriptionTranslationKey',
                'formatCreationForSSRShort',
                'shortDescription',
                'English short description',
            ],
            'full description fallback' => [
                'fullDescriptionTranslationKey',
                'formatCreationForSSRFull',
                'fullDescription',
                'English full description',
            ],
        ];
    }

    #[Test]
    #[DataProvider('creationTranslationProvider')]
    public function test_creation_translation_fallback(string $translationKeyField, string $formatMethod, string $resultField, string $text): void
    {
        app()->setLocale('es');
        config(['app.fallback_locale' => 'en']);

        $creation = Creation::factory()->create();

        // Create only English translation
        $creation->{$translationKeyField}->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $creation->{$translationKeyField}->id,
            'locale' => 'en',
            'text' => $text,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->$formatMethod($creation);

        $this->assertEquals($text, $result[$resultField]);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

    /**
     * Data provider for GitHub error scenarios
     */
    public static function githubErrorScenariosProvider(): array
    {
        return [
            'no GitHub URL' => [
                null,
                null,
                null,
            ],
            'non-GitHub URL' => [
                'https://gitlab.com/owner/repo',
                null,
                null,
            ],
            'GitHub API 404 error' => [
                'https://github.com/owner/nonexistent',
                'api.github.com/repos/owner/nonexistent',
                ['response' => null, 'status' => 404],
            ],
            'private GitHub repo' => [
                'https://github.com/owner/private-repo',
                'api.github.com/repos/owner/private-repo',
                ['response' => ['message' => 'Not Found'], 'status' => 404],
            ],
            'GitHub rate limit' => [
                'https://github.com/owner/repo',
                'api.github.com/repos/owner/repo',
                ['response' => ['message' => 'API rate limit exceeded'], 'status' => 403],
            ],
            'GitHub server error' => [
                'https://github.com/owner/repo',
                'api.github.com/repos/owner/repo',
                ['response' => null, 'status' => 500],
            ],
        ];
    }

    #[Test]
    #[DataProvider('githubErrorScenariosProvider')]
    public function test_github_error_scenarios(?string $sourceCodeUrl, ?string $apiEndpoint, ?array $httpMock): void
    {
        $creation = Creation::factory()->create([
            'name' => 'Test Creation',
            'source_code_url' => $sourceCodeUrl,
        ]);

        if ($httpMock !== null) {
            Http::fake([
                $apiEndpoint => Http::response($httpMock['response'], $httpMock['status']),
            ]);
        }

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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
            $markdown = ContentMarkdown::factory()->create();
            BlogPostContent::factory()->create([
                'blog_post_id' => $post->id,
                'content_type' => ContentMarkdown::class,
                'content_id' => $markdown->id,
                'order' => 1,
            ]);
        }

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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
        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
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
        $service = new PublicControllersService(new CustomEmojiResolverService);
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
        $gallery = ContentGallery::factory()->create();
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
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostBySlug('test-post-gallery');

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals(ContentGallery::class, $result['contents'][0]['content_type']);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('packagistData', $result);
        $this->assertNotNull($result['packagistData']);
        $this->assertEquals('vendor/package', $result['packagistData']['name']);
        $this->assertEquals('Test package description', $result['packagistData']['description']);
        $this->assertEquals(50000, $result['packagistData']['downloads']);
    }

    /**
     * Data provider for Packagist error scenarios
     */
    public static function packagistErrorScenariosProvider(): array
    {
        return [
            'Packagist API error' => [
                'https://packagist.org/packages/vendor/nonexistent',
                'packagist.org/packages/vendor/nonexistent.json',
                ['response' => null, 'status' => 404],
            ],
            'non-Packagist URL' => [
                'https://example.com',
                null,
                null,
            ],
        ];
    }

    #[Test]
    #[DataProvider('packagistErrorScenariosProvider')]
    public function test_packagist_error_scenarios(string $externalUrl, ?string $apiEndpoint, ?array $httpMock): void
    {
        $creation = Creation::factory()->create([
            'external_url' => $externalUrl,
        ]);

        if ($httpMock !== null) {
            Http::fake([
                $apiEndpoint => Http::response($httpMock['response'], $httpMock['status']),
            ]);
        }

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $markdown = ContentMarkdown::factory()->create(['translation_key_id' => $markdownKey->id]);
        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatBlogPostForSSRShort($post);

        $this->assertStringNotContainsString('#', $result['excerpt']);
        $this->assertStringNotContainsString('*', $result['excerpt']);
        $this->assertStringNotContainsString('_', $result['excerpt']);
        $this->assertStringNotContainsString('`', $result['excerpt']);
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

        $markdown = ContentMarkdown::factory()->create(['translation_key_id' => $markdownKey->id]);
        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
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

        $markdown = ContentMarkdown::factory()->create(['translation_key_id' => $markdownKey->id]);
        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatBlogPostForSSRShort($post);

        $this->assertEquals('', $result['excerpt']);
    }

    #[Test]
    public function test_get_blog_post_by_slug_with_video_content(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'slug' => 'test-post-with-video',
            'category_id' => $category->id,
        ]);

        // Create video content
        $video = \App\Models\Video::factory()->readyAndPublic()->create([
            'name' => 'Test Video',
            'bunny_video_id' => 'test-video-123',
        ]);

        $blogContentVideo = \App\Models\ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        // Add caption translation
        $captionKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'en',
            'text' => 'Video caption text',
        ]);
        $blogContentVideo->update(['caption_translation_key_id' => $captionKey->id]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => \App\Models\ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostBySlug('test-post-with-video');

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals(\App\Models\ContentVideo::class, $result['contents'][0]['content_type']);
        $this->assertArrayHasKey('video', $result['contents'][0]);
        $this->assertEquals($video->id, $result['contents'][0]['video']['id']);
        $this->assertEquals('test-video-123', $result['contents'][0]['video']['bunnyVideoId']);
        $this->assertEquals('Test Video', $result['contents'][0]['video']['name']);
        $this->assertEquals('Video caption text', $result['contents'][0]['video']['caption']);
        $this->assertArrayHasKey('coverPicture', $result['contents'][0]['video']);
    }

    #[Test]
    public function test_get_blog_post_by_slug_excludes_private_videos(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'slug' => 'test-post-private-video',
            'category_id' => $category->id,
        ]);

        // Create private video (ready but private)
        $video = \App\Models\Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PRIVATE,
        ]);

        $blogContentVideo = \App\Models\ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => \App\Models\ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostBySlug('test-post-private-video');

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        // Video should NOT be included because it's private
        $this->assertArrayNotHasKey('video', $result['contents'][0]);
    }

    #[Test]
    public function test_get_blog_post_by_slug_excludes_transcoding_videos(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'slug' => 'test-post-transcoding-video',
            'category_id' => $category->id,
        ]);

        // Create transcoding video (public but not ready)
        $video = \App\Models\Video::factory()->create([
            'status' => VideoStatus::TRANSCODING,
            'visibility' => VideoVisibility::PUBLIC,
        ]);

        $blogContentVideo = \App\Models\ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => \App\Models\ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostBySlug('test-post-transcoding-video');

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        // Video should NOT be included because it's not ready
        $this->assertArrayNotHasKey('video', $result['contents'][0]);
    }

    #[Test]
    public function test_get_blog_post_by_slug_with_video_caption(): void
    {
        app()->setLocale('fr');

        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'slug' => 'test-post-video-caption',
            'category_id' => $category->id,
        ]);

        $video = \App\Models\Video::factory()->readyAndPublic()->create();
        $blogContentVideo = \App\Models\ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        // Create caption with French translation
        $captionKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'fr',
            'text' => 'Légende de la vidéo en français',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'en',
            'text' => 'Video caption in English',
        ]);
        $blogContentVideo->update(['caption_translation_key_id' => $captionKey->id]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => \App\Models\ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostBySlug('test-post-video-caption');

        $this->assertNotNull($result);
        // Should use French translation based on current locale
        $this->assertEquals('Légende de la vidéo en français', $result['contents'][0]['video']['caption']);
    }

    #[Test]
    public function test_get_blog_post_by_slug_with_video_without_caption(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'slug' => 'test-post-video-no-caption',
            'category_id' => $category->id,
        ]);

        $video = \App\Models\Video::factory()->readyAndPublic()->create();
        $blogContentVideo = \App\Models\ContentVideo::factory()->create([
            'video_id' => $video->id,
            'caption_translation_key_id' => null,
        ]);

        BlogPostContent::factory()->create([
            'blog_post_id' => $post->id,
            'content_type' => \App\Models\ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostBySlug('test-post-video-no-caption');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('video', $result['contents'][0]);
        $this->assertNull($result['contents'][0]['video']['caption']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_with_markdown(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'type' => BlogPostType::ARTICLE,
        ]);

        // Add markdown content
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => 'This is the draft blog content.',
        ]);

        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertEquals($draft->id, $result['id']);
        $this->assertEquals($draft->slug, $result['slug']);
        $this->assertTrue($result['isPreview']);
        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals('This is the draft blog content.', $result['contents'][0]['markdown']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_has_is_preview_flag(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertArrayHasKey('isPreview', $result);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_with_gallery(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        // Add gallery content
        $gallery = ContentGallery::factory()->create();
        $pictures = \App\Models\Picture::factory()->count(3)->create();

        foreach ($pictures as $index => $picture) {
            // Create caption for first picture only
            if ($index === 0) {
                $captionKey = TranslationKey::factory()->create();
                Translation::factory()->create([
                    'translation_key_id' => $captionKey->id,
                    'locale' => 'en',
                    'text' => 'Draft gallery caption',
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

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals(ContentGallery::class, $result['contents'][0]['content_type']);
        $this->assertArrayHasKey('gallery', $result['contents'][0]);
        $this->assertCount(3, $result['contents'][0]['gallery']['pictures']);
        $this->assertEquals('Draft gallery caption', $result['contents'][0]['gallery']['pictures'][0]['caption']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_includes_ready_private_videos(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        // Create private video (ready but private) - should be included in preview
        $video = \App\Models\Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PRIVATE,
            'name' => 'Private Draft Video',
            'bunny_video_id' => 'test-private-123',
        ]);

        $blogContentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        // Video SHOULD be included in preview even if private
        $this->assertArrayHasKey('video', $result['contents'][0]);
        $this->assertEquals($video->id, $result['contents'][0]['video']['id']);
        $this->assertEquals('test-private-123', $result['contents'][0]['video']['bunnyVideoId']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_excludes_transcoding_videos(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        // Create transcoding video - should NOT be included even in preview
        $video = \App\Models\Video::factory()->create([
            'status' => VideoStatus::TRANSCODING,
            'visibility' => VideoVisibility::PUBLIC,
        ]);

        $blogContentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertCount(1, $result['contents']);
        // Video should NOT be included because it's not ready
        $this->assertArrayNotHasKey('video', $result['contents'][0]);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_with_game_review(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'type' => BlogPostType::GAME_REVIEW,
        ]);

        // Create game review draft
        $gameReviewDraft = GameReviewDraft::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'game_title' => 'Test Draft Game',
            'rating' => GameReviewRating::POSITIVE,
            'platforms' => ['PC', 'PS5'],
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('gameReview', $result);
        $this->assertEquals('Test Draft Game', $result['gameReview']['gameTitle']);
        $this->assertEquals(GameReviewRating::POSITIVE, $result['gameReview']['rating']);
        $this->assertEquals(['PC', 'PS5'], $result['gameReview']['platforms']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_with_no_content(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertEquals('', $result['excerpt']);
        $this->assertCount(0, $result['contents']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_with_multiple_content_blocks(): void
    {
        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        // Add markdown content (order 1)
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => 'First markdown block',
        ]);
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        // Add gallery content (order 2)
        $gallery = ContentGallery::factory()->create();
        $picture = \App\Models\Picture::factory()->create();
        $gallery->pictures()->attach($picture->id, ['order' => 0]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 2,
        ]);

        // Add video content (order 3)
        $video = \App\Models\Video::factory()->readyAndPublic()->create();
        $blogContentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 3,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertCount(3, $result['contents']);
        $this->assertEquals(1, $result['contents'][0]['order']);
        $this->assertEquals(2, $result['contents'][1]['order']);
        $this->assertEquals(3, $result['contents'][2]['order']);
        $this->assertEquals(ContentMarkdown::class, $result['contents'][0]['content_type']);
        $this->assertEquals(ContentGallery::class, $result['contents'][1]['content_type']);
        $this->assertEquals(ContentVideo::class, $result['contents'][2]['content_type']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_translation_fallback(): void
    {
        app()->setLocale('es');
        config(['app.fallback_locale' => 'en']);

        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        // Create only English translation for title
        $draft->titleTranslationKey->translations()->delete();
        Translation::factory()->create([
            'translation_key_id' => $draft->titleTranslationKey->id,
            'locale' => 'en',
            'text' => 'English Draft Title',
        ]);

        // Add markdown content with only English translation
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => 'English draft content',
        ]);
        $markdown = ContentMarkdown::factory()->create([
            'translation_key_id' => $markdownKey->id,
        ]);
        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertEquals('English Draft Title', $result['title']);
        $this->assertEquals('English draft content', $result['contents'][0]['markdown']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_with_video_caption(): void
    {
        app()->setLocale('fr');

        $category = BlogCategory::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
        ]);

        $video = \App\Models\Video::factory()->readyAndPublic()->create();
        $blogContentVideo = ContentVideo::factory()->create([
            'video_id' => $video->id,
        ]);

        // Create caption with French translation
        $captionKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'fr',
            'text' => 'Légende du brouillon en français',
        ]);
        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'en',
            'text' => 'Draft caption in English',
        ]);
        $blogContentVideo->update(['caption_translation_key_id' => $captionKey->id]);

        BlogPostDraftContent::factory()->create([
            'blog_post_draft_id' => $draft->id,
            'content_type' => ContentVideo::class,
            'content_id' => $blogContentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        // Should use French translation based on current locale
        $this->assertEquals('Légende du brouillon en français', $result['contents'][0]['video']['caption']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_get_blog_post_draft_for_preview_includes_category_and_cover(): void
    {
        $category = BlogCategory::factory()->create();
        $coverPicture = \App\Models\Picture::factory()->create();
        $draft = BlogPostDraft::factory()->create([
            'category_id' => $category->id,
            'cover_picture_id' => $coverPicture->id,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->getBlogPostDraftForPreview($draft);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('coverImage', $result);
        $this->assertNotNull($result['coverImage']);
        $this->assertEquals($coverPicture->filename, $result['coverImage']['filename']);
        $this->assertTrue($result['isPreview']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_content_markdown_block(): void
    {
        $creation = Creation::factory()->create();

        // Create a markdown content block
        $translationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'This is markdown content with :custom_emoji:',
        ]);

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $emojiResolverMock = $this->createMock(CustomEmojiResolverService::class);
        $emojiResolverMock->expects($this->once())
            ->method('resolveEmojisInMarkdown')
            ->with('This is markdown content with :custom_emoji:')
            ->willReturn('This is markdown content with <picture>...</picture>');

        $service = new PublicControllersService($emojiResolverMock);
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals(ContentMarkdown::class, $result['contents'][0]['content_type']);
        $this->assertEquals(1, $result['contents'][0]['order']);
        $this->assertEquals('This is markdown content with <picture>...</picture>', $result['contents'][0]['markdown']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_content_markdown_emoji_resolution_failure(): void
    {
        $creation = Creation::factory()->create();

        // Create a markdown content block
        $translationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'This is markdown content with :broken_emoji:',
        ]);

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $emojiResolverMock = $this->createMock(CustomEmojiResolverService::class);
        $emojiResolverMock->expects($this->once())
            ->method('resolveEmojisInMarkdown')
            ->with('This is markdown content with :broken_emoji:')
            ->willThrowException(new \Exception('Emoji not found'));

        $service = new PublicControllersService($emojiResolverMock);
        $result = $service->formatCreationForSSRFull($creation);

        // Should fallback to original markdown when emoji resolution fails
        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals('This is markdown content with :broken_emoji:', $result['contents'][0]['markdown']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_content_markdown_translation_fallback(): void
    {
        app()->setLocale('es'); // Set a locale that doesn't have translations
        config(['app.fallback_locale' => 'en']);

        $creation = Creation::factory()->create();

        // Create a markdown content block with only English translation
        $translationKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en',
            'text' => 'English markdown content',
        ]);

        $markdown = ContentMarkdown::create([
            'translation_key_id' => $translationKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        // Should fallback to English when Spanish is not available
        $this->assertArrayHasKey('contents', $result);
        $this->assertEquals('English markdown content', $result['contents'][0]['markdown']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_content_gallery_block_with_captions(): void
    {
        $creation = Creation::factory()->create();

        // Create pictures with captions
        $picture1 = \App\Models\Picture::factory()->create();
        $picture2 = \App\Models\Picture::factory()->create();

        $captionKey1 = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $captionKey1->id,
            'locale' => 'en',
            'text' => 'Caption for picture 1',
        ]);

        $captionKey2 = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $captionKey2->id,
            'locale' => 'en',
            'text' => 'Caption for picture 2',
        ]);

        // Create gallery
        $gallery = ContentGallery::create([
            'layout' => 'grid',
            'columns' => 3,
        ]);

        // Attach pictures with captions
        $gallery->pictures()->attach([
            $picture1->id => [
                'order' => 1,
                'caption_translation_key_id' => $captionKey1->id,
            ],
            $picture2->id => [
                'order' => 2,
                'caption_translation_key_id' => $captionKey2->id,
            ],
        ]);

        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals(ContentGallery::class, $result['contents'][0]['content_type']);
        $this->assertArrayHasKey('gallery', $result['contents'][0]);
        $this->assertCount(2, $result['contents'][0]['gallery']['pictures']);

        // Verify captions are present (pictures are ordered by their 'order' pivot value)
        $resultPicture1 = collect($result['contents'][0]['gallery']['pictures'])->firstWhere('filename', $picture1->filename);
        $resultPicture2 = collect($result['contents'][0]['gallery']['pictures'])->firstWhere('filename', $picture2->filename);

        $this->assertEquals('Caption for picture 1', $resultPicture1['caption']);
        $this->assertEquals('Caption for picture 2', $resultPicture2['caption']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_content_gallery_block_mixed_captions(): void
    {
        $creation = Creation::factory()->create();

        // Create pictures - one with caption, one without
        $picture1 = \App\Models\Picture::factory()->create();
        $picture2 = \App\Models\Picture::factory()->create();

        $captionKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'en',
            'text' => 'Only picture 1 has a caption',
        ]);

        // Create gallery
        $gallery = ContentGallery::create([
            'layout' => 'masonry',
            'columns' => 2,
        ]);

        // Attach pictures - only first one has caption
        $gallery->pictures()->attach([
            $picture1->id => [
                'order' => 1,
                'caption_translation_key_id' => $captionKey->id,
            ],
            $picture2->id => [
                'order' => 2,
                'caption_translation_key_id' => null,
            ],
        ]);

        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        $resultPicture1 = collect($result['contents'][0]['gallery']['pictures'])->firstWhere('filename', $picture1->filename);
        $resultPicture2 = collect($result['contents'][0]['gallery']['pictures'])->firstWhere('filename', $picture2->filename);

        // Picture 1 should have caption
        $this->assertArrayHasKey('caption', $resultPicture1);
        $this->assertEquals('Only picture 1 has a caption', $resultPicture1['caption']);

        // Picture 2 should NOT have caption key
        $this->assertArrayNotHasKey('caption', $resultPicture2);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_content_video_block_filters_by_status_and_visibility(): void
    {
        $creation = Creation::factory()->create();

        // Create videos with different statuses and visibilities
        $readyPublicVideo = \App\Models\Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PUBLIC,
        ]);

        $readyPrivateVideo = \App\Models\Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PRIVATE,
        ]);

        $transcodingPublicVideo = \App\Models\Video::factory()->create([
            'status' => VideoStatus::TRANSCODING,
            'visibility' => VideoVisibility::PUBLIC,
        ]);

        // Create content video blocks for each
        $contentVideo1 = ContentVideo::create(['video_id' => $readyPublicVideo->id]);
        $contentVideo2 = ContentVideo::create(['video_id' => $readyPrivateVideo->id]);
        $contentVideo3 = ContentVideo::create(['video_id' => $transcodingPublicVideo->id]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo1->id,
            'order' => 1,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo2->id,
            'order' => 2,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo3->id,
            'order' => 3,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(3, $result['contents']);

        // Only the READY + PUBLIC video should have 'video' key
        $this->assertArrayHasKey('video', $result['contents'][0]);
        $this->assertEquals($readyPublicVideo->id, $result['contents'][0]['video']['id']);

        // READY + PRIVATE should NOT have 'video' key
        $this->assertArrayNotHasKey('video', $result['contents'][1]);

        // TRANSCODING + PUBLIC should NOT have 'video' key
        $this->assertArrayNotHasKey('video', $result['contents'][2]);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_content_video_block_with_caption(): void
    {
        $creation = Creation::factory()->create();

        $video = \App\Models\Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PUBLIC,
        ]);

        // Create caption
        $captionKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $captionKey->id,
            'locale' => 'en',
            'text' => 'This is a video caption',
        ]);

        $contentVideo = ContentVideo::create([
            'video_id' => $video->id,
            'caption_translation_key_id' => $captionKey->id,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('video', $result['contents'][0]);
        $this->assertEquals('This is a video caption', $result['contents'][0]['video']['caption']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_mixed_content_blocks_ordered_correctly(): void
    {
        $creation = Creation::factory()->create();

        // Create markdown block (order 2)
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => 'Markdown content',
        ]);
        $markdown = ContentMarkdown::create(['translation_key_id' => $markdownKey->id]);

        // Create gallery block (order 1)
        $gallery = ContentGallery::create(['layout' => 'grid', 'columns' => 2]);
        $picture = \App\Models\Picture::factory()->create();
        $gallery->pictures()->attach($picture->id, ['order' => 1]);

        // Create video block (order 3)
        $video = \App\Models\Video::factory()->create([
            'status' => VideoStatus::READY,
            'visibility' => VideoVisibility::PUBLIC,
        ]);
        $contentVideo = ContentVideo::create(['video_id' => $video->id]);

        // Add content blocks in non-sequential order
        $creation->contents()->create([
            'content_type' => ContentGallery::class,
            'content_id' => $gallery->id,
            'order' => 1,
        ]);

        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 2,
        ]);

        $creation->contents()->create([
            'content_type' => ContentVideo::class,
            'content_id' => $contentVideo->id,
            'order' => 3,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(3, $result['contents']);

        // Verify ordering
        $this->assertEquals(1, $result['contents'][0]['order']);
        $this->assertEquals(ContentGallery::class, $result['contents'][0]['content_type']);

        $this->assertEquals(2, $result['contents'][1]['order']);
        $this->assertEquals(ContentMarkdown::class, $result['contents'][1]['content_type']);

        $this->assertEquals(3, $result['contents'][2]['order']);
        $this->assertEquals(ContentVideo::class, $result['contents'][2]['content_type']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_with_no_content_blocks_returns_empty_array(): void
    {
        $creation = Creation::factory()->create();

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        $this->assertArrayHasKey('contents', $result);
        $this->assertIsArray($result['contents']);
        $this->assertEmpty($result['contents']);
    }

    #[Test]
    public function test_format_creation_for_ssr_full_backward_compatibility_with_full_description(): void
    {
        $creation = Creation::factory()->create();

        // Add both fullDescription (legacy) and content blocks (new system)
        $fullDescKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $fullDescKey->id,
            'locale' => 'en',
            'text' => 'Legacy full description',
        ]);
        $creation->update(['full_description_translation_key_id' => $fullDescKey->id]);

        // Also add a content block
        $markdownKey = TranslationKey::factory()->create();
        Translation::factory()->create([
            'translation_key_id' => $markdownKey->id,
            'locale' => 'en',
            'text' => 'New content block',
        ]);
        $markdown = ContentMarkdown::create(['translation_key_id' => $markdownKey->id]);
        $creation->contents()->create([
            'content_type' => ContentMarkdown::class,
            'content_id' => $markdown->id,
            'order' => 1,
        ]);

        $service = new PublicControllersService(new CustomEmojiResolverService);
        $result = $service->formatCreationForSSRFull($creation);

        // Both should be present for backward compatibility
        $this->assertArrayHasKey('fullDescription', $result);
        $this->assertEquals('Legacy full description', $result['fullDescription']);

        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertEquals('New content block', $result['contents'][0]['markdown']);
    }
}
