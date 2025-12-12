<?php

namespace Tests\Unit\Services\Formatters;

use App\Enums\CreationType;
use App\Enums\TechnologyType;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Creation;
use App\Models\CreationContent;
use App\Models\Feature;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Screenshot;
use App\Models\Technology;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\Formatters\ContentBlockFormatter;
use App\Services\Formatters\CreationFormatter;
use App\Services\Formatters\MediaFormatter;
use App\Services\Formatters\TranslationHelper;
use App\Services\GitHubService;
use App\Services\PackagistService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationFormatter::class)]
class CreationFormatterTest extends TestCase
{
    private CreationFormatter $formatter;

    private MediaFormatter&MockInterface $mediaFormatter;

    private TranslationHelper&MockInterface $translationHelper;

    private ContentBlockFormatter&MockInterface $contentBlockFormatter;

    private GitHubService&MockInterface $gitHubService;

    private PackagistService&MockInterface $packagistService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaFormatter = Mockery::mock(MediaFormatter::class);
        $this->translationHelper = Mockery::mock(TranslationHelper::class);
        $this->contentBlockFormatter = Mockery::mock(ContentBlockFormatter::class);
        $this->gitHubService = Mockery::mock(GitHubService::class);
        $this->packagistService = Mockery::mock(PackagistService::class);

        // Mock the Technology query in constructor
        $this->formatter = $this->createFormatterWithMockedTechnologyCount([1 => 5, 2 => 3]);
    }

    #[Test]
    public function it_formats_creation_short(): void
    {
        $creation = $this->createMockCreation();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Short description', 'Technology description');

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2024', null);

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'picture.jpg']);

        $result = $this->formatter->formatShort($creation);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Test Creation', $result['name']);
        $this->assertEquals('test-creation', $result['slug']);
        $this->assertEquals(CreationType::PORTFOLIO, $result['type']);
        $this->assertEquals('Short description', $result['shortDescription']);
        $this->assertEquals(['filename' => 'picture.jpg'], $result['logo']);
        $this->assertEquals(['filename' => 'picture.jpg'], $result['coverImage']);
        $this->assertEquals('Janvier 2024', $result['startedAtFormatted']);
        $this->assertNull($result['endedAtFormatted']);
        $this->assertCount(1, $result['technologies']);
    }

    #[Test]
    public function it_formats_creation_short_without_optional_fields(): void
    {
        $creation = $this->createMockCreationMinimal();

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2024', null);

        $result = $this->formatter->formatShort($creation);

        $this->assertEquals(1, $result['id']);
        $this->assertNull($result['shortDescription']);
        $this->assertNull($result['logo']);
        $this->assertNull($result['coverImage']);
        $this->assertEmpty($result['technologies']);
    }

    #[Test]
    public function it_formats_creation_full_with_all_data(): void
    {
        $creation = $this->createMockCreationFull();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn(
                'Short description',
                'Tech description',
                'Full description',
                'Feature title',
                'Feature description',
                'Screenshot caption'
            );

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2024', null);

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'picture.jpg']);

        $this->mediaFormatter
            ->shouldReceive('formatVideo')
            ->andReturn([
                'id' => 1,
                'bunnyVideoId' => 'bunny-123',
                'name' => 'Test Video',
                'coverPicture' => ['filename' => 'cover.jpg'],
                'libraryId' => 'test-lib',
            ]);

        $this->contentBlockFormatter
            ->shouldReceive('format')
            ->andReturn([
                'id' => 1,
                'order' => 0,
                'content_type' => 'App\\Models\\ContentMarkdown',
                'markdown' => '# Hello',
            ]);

        $this->gitHubService
            ->shouldReceive('getRepositoryData')
            ->andReturn(null);

        $this->packagistService
            ->shouldReceive('getPackageData')
            ->andReturn(null);

        $result = $this->formatter->formatFull($creation);

        $this->assertEquals('Full description', $result['fullDescription']);
        $this->assertEquals('https://example.com', $result['externalUrl']);
        $this->assertEquals('https://github.com/test/repo', $result['sourceCodeUrl']);
        $this->assertArrayHasKey('contents', $result);
        $this->assertArrayHasKey('features', $result);
        $this->assertArrayHasKey('screenshots', $result);
        $this->assertArrayHasKey('people', $result);
        $this->assertArrayHasKey('videos', $result);
        $this->assertArrayHasKey('githubData', $result);
        $this->assertArrayHasKey('packagistData', $result);
    }

    #[Test]
    public function it_includes_github_data_when_source_code_url_is_github(): void
    {
        $creation = $this->createMockCreationFull();
        $creation->source_code_url = 'https://github.com/test/repo';

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Description');

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2024', null);

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'picture.jpg']);

        $this->mediaFormatter
            ->shouldReceive('formatVideo')
            ->andReturn([
                'id' => 1,
                'bunnyVideoId' => 'bunny-123',
                'name' => 'Test Video',
                'coverPicture' => ['filename' => 'cover.jpg'],
                'libraryId' => 'test-lib',
            ]);

        $this->contentBlockFormatter
            ->shouldReceive('format')
            ->andReturn([]);

        $githubData = [
            'name' => 'test-repo',
            'stars' => 100,
            'forks' => 20,
        ];

        $githubLanguages = ['PHP' => 80.5, 'JavaScript' => 19.5];

        $this->gitHubService
            ->shouldReceive('getRepositoryData')
            ->with('https://github.com/test/repo')
            ->andReturn($githubData);

        $this->gitHubService
            ->shouldReceive('getRepositoryLanguages')
            ->with('https://github.com/test/repo')
            ->andReturn($githubLanguages);

        $this->packagistService
            ->shouldReceive('getPackageData')
            ->andReturn(null);

        $result = $this->formatter->formatFull($creation);

        $this->assertEquals($githubData, $result['githubData']);
        $this->assertEquals($githubLanguages, $result['githubLanguages']);
    }

    #[Test]
    public function it_includes_packagist_data_when_external_url_is_packagist(): void
    {
        $creation = $this->createMockCreationFull();
        $creation->external_url = 'https://packagist.org/packages/test/package';

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Description');

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2024', null);

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'picture.jpg']);

        $this->mediaFormatter
            ->shouldReceive('formatVideo')
            ->andReturn([
                'id' => 1,
                'bunnyVideoId' => 'bunny-123',
                'name' => 'Test Video',
                'coverPicture' => ['filename' => 'cover.jpg'],
                'libraryId' => 'test-lib',
            ]);

        $this->contentBlockFormatter
            ->shouldReceive('format')
            ->andReturn([]);

        $this->gitHubService
            ->shouldReceive('getRepositoryData')
            ->andReturn(null);

        $packagistData = [
            'name' => 'test/package',
            'downloads' => 10000,
        ];

        $this->packagistService
            ->shouldReceive('getPackageData')
            ->with('https://packagist.org/packages/test/package')
            ->andReturn($packagistData);

        $result = $this->formatter->formatFull($creation);

        $this->assertEquals($packagistData, $result['packagistData']);
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

    #[Test]
    public function it_filters_videos_by_visibility_and_status(): void
    {
        $creation = $this->createMockCreationWithVideos();

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Description');

        $this->translationHelper
            ->shouldReceive('formatDate')
            ->andReturn('Janvier 2024', null);

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->andReturn(['filename' => 'picture.jpg']);

        // Only one video should be formatted (the public + ready one)
        $this->mediaFormatter
            ->shouldReceive('formatVideo')
            ->once()
            ->andReturn([
                'id' => 1,
                'bunnyVideoId' => 'bunny-123',
                'name' => 'Public Ready Video',
                'coverPicture' => ['filename' => 'cover.jpg'],
                'libraryId' => 'test-lib',
            ]);

        $this->contentBlockFormatter
            ->shouldReceive('format')
            ->andReturn([]);

        $this->gitHubService
            ->shouldReceive('getRepositoryData')
            ->andReturn(null);

        $this->packagistService
            ->shouldReceive('getPackageData')
            ->andReturn(null);

        $result = $this->formatter->formatFull($creation);

        $this->assertCount(1, $result['videos']);
        $this->assertEquals('Public Ready Video', $result['videos'][0]['name']);
    }

    /**
     * Create formatter with mocked technology count to avoid database calls.
     *
     * @param  array<int, int|null>  $counts
     */
    private function createFormatterWithMockedTechnologyCount(array $counts): CreationFormatter
    {
        // Create a partial mock that bypasses the constructor's database call
        $formatter = new CreationFormatter(
            $this->mediaFormatter,
            $this->translationHelper,
            $this->contentBlockFormatter,
            $this->gitHubService,
            $this->packagistService,
        );

        $formatter->setCreationCountByTechnology($counts);

        return $formatter;
    }

    /**
     * Create a mock Creation for testing.
     */
    private function createMockCreation(): Creation&MockInterface
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $descriptionKey */
        $descriptionKey = Mockery::mock(TranslationKey::class)->makePartial();
        $descriptionKey->translations = $translations;

        /** @var Picture&MockInterface $logo */
        $logo = Mockery::mock(Picture::class)->makePartial();

        /** @var Picture&MockInterface $coverImage */
        $coverImage = Mockery::mock(Picture::class)->makePartial();

        $technology = $this->createMockTechnology();

        /** @var Creation&MockInterface $creation */
        $creation = Mockery::mock(Creation::class)->makePartial();
        $creation->id = 1;
        $creation->name = 'Test Creation';
        $creation->slug = 'test-creation';
        $creation->type = CreationType::PORTFOLIO;
        $creation->started_at = Carbon::create(2024, 1, 15)->format('Y-m-d');
        $creation->ended_at = null;
        $creation->shortDescriptionTranslationKey = $descriptionKey;
        $creation->logo = $logo;
        $creation->coverImage = $coverImage;
        $creation->technologies = new Collection([$technology]);

        return $creation;
    }

    /**
     * Create a minimal mock Creation without optional fields.
     */
    private function createMockCreationMinimal(): Creation&MockInterface
    {
        /** @var Creation&MockInterface $creation */
        $creation = Mockery::mock(Creation::class)->makePartial();
        $creation->id = 1;
        $creation->name = 'Minimal Creation';
        $creation->slug = 'minimal-creation';
        $creation->type = CreationType::OTHER;
        $creation->started_at = Carbon::create(2024, 1, 15)->format('Y-m-d');
        $creation->ended_at = null;
        $creation->shortDescriptionTranslationKey = null;
        $creation->logo = null;
        $creation->coverImage = null;
        $creation->technologies = new Collection;

        return $creation;
    }

    /**
     * Create a full mock Creation with all relations for testing formatFull.
     */
    private function createMockCreationFull(): Creation&MockInterface
    {
        $creation = $this->createMockCreation();

        $translations = new Collection;

        /** @var TranslationKey&MockInterface $fullDescriptionKey */
        $fullDescriptionKey = Mockery::mock(TranslationKey::class)->makePartial();
        $fullDescriptionKey->translations = $translations;

        /** @var TranslationKey&MockInterface $featureTitleKey */
        $featureTitleKey = Mockery::mock(TranslationKey::class)->makePartial();
        $featureTitleKey->translations = $translations;

        /** @var TranslationKey&MockInterface $featureDescKey */
        $featureDescKey = Mockery::mock(TranslationKey::class)->makePartial();
        $featureDescKey->translations = $translations;

        /** @var Picture&MockInterface $featurePicture */
        $featurePicture = Mockery::mock(Picture::class)->makePartial();

        /** @var Feature&MockInterface $feature */
        $feature = Mockery::mock(Feature::class)->makePartial();
        $feature->id = 1;
        $feature->titleTranslationKey = $featureTitleKey;
        $feature->descriptionTranslationKey = $featureDescKey;
        $feature->picture = $featurePicture;

        /** @var TranslationKey&MockInterface $captionKey */
        $captionKey = Mockery::mock(TranslationKey::class)->makePartial();
        $captionKey->translations = $translations;

        /** @var Picture&MockInterface $screenshotPicture */
        $screenshotPicture = Mockery::mock(Picture::class)->makePartial();

        /** @var Screenshot&MockInterface $screenshot */
        $screenshot = Mockery::mock(Screenshot::class)->makePartial();
        $screenshot->id = 1;
        $screenshot->order = 0;
        $screenshot->picture = $screenshotPicture;
        $screenshot->captionTranslationKey = $captionKey;

        /** @var Picture&MockInterface $personPicture */
        $personPicture = Mockery::mock(Picture::class)->makePartial();

        /** @var Person&MockInterface $person */
        $person = Mockery::mock(Person::class)->makePartial();
        $person->id = 1;
        $person->name = 'John Doe';
        $person->url = 'https://johndoe.com';
        $person->picture = $personPicture;

        /** @var Picture&MockInterface $videoCover */
        $videoCover = Mockery::mock(Picture::class)->makePartial();

        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;
        $video->bunny_video_id = 'bunny-123';
        $video->name = 'Test Video';
        $video->status = VideoStatus::READY;
        $video->visibility = VideoVisibility::PUBLIC;
        $video->coverPicture = $videoCover;

        /** @var CreationContent&MockInterface $content */
        $content = Mockery::mock(CreationContent::class)->makePartial();
        $content->id = 1;
        $content->order = 0;

        $creation->fullDescriptionTranslationKey = $fullDescriptionKey;
        $creation->external_url = 'https://example.com';
        $creation->source_code_url = 'https://github.com/test/repo';
        $creation->contents = new Collection([$content]);
        $creation->features = new Collection([$feature]);
        $creation->screenshots = new Collection([$screenshot]);
        $creation->people = new Collection([$person]);
        $creation->videos = new Collection([$video]);

        return $creation;
    }

    /**
     * Create a mock Creation with mixed video visibility/status for filtering test.
     */
    private function createMockCreationWithVideos(): Creation&MockInterface
    {
        $creation = $this->createMockCreation();

        /** @var Picture&MockInterface $videoCover */
        $videoCover = Mockery::mock(Picture::class)->makePartial();

        // Public and ready - should be included
        /** @var Video&MockInterface $video1 */
        $video1 = Mockery::mock(Video::class)->makePartial();
        $video1->id = 1;
        $video1->bunny_video_id = 'bunny-123';
        $video1->name = 'Public Ready Video';
        $video1->status = VideoStatus::READY;
        $video1->visibility = VideoVisibility::PUBLIC;
        $video1->coverPicture = $videoCover;

        // Private - should be excluded
        /** @var Video&MockInterface $video2 */
        $video2 = Mockery::mock(Video::class)->makePartial();
        $video2->id = 2;
        $video2->bunny_video_id = 'bunny-456';
        $video2->name = 'Private Video';
        $video2->status = VideoStatus::READY;
        $video2->visibility = VideoVisibility::PRIVATE;
        $video2->coverPicture = $videoCover;

        // Not ready - should be excluded
        /** @var Video&MockInterface $video3 */
        $video3 = Mockery::mock(Video::class)->makePartial();
        $video3->id = 3;
        $video3->bunny_video_id = 'bunny-789';
        $video3->name = 'Transcoding Video';
        $video3->status = VideoStatus::TRANSCODING;
        $video3->visibility = VideoVisibility::PUBLIC;
        $video3->coverPicture = $videoCover;

        $translations = new Collection;

        /** @var TranslationKey&MockInterface $fullDescriptionKey */
        $fullDescriptionKey = Mockery::mock(TranslationKey::class)->makePartial();
        $fullDescriptionKey->translations = $translations;

        $creation->fullDescriptionTranslationKey = $fullDescriptionKey;
        $creation->external_url = null;
        $creation->source_code_url = null;
        $creation->contents = new Collection;
        $creation->features = new Collection;
        $creation->screenshots = new Collection;
        $creation->people = new Collection;
        $creation->videos = new Collection([$video1, $video2, $video3]);

        return $creation;
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
