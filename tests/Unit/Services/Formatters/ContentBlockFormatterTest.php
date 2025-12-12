<?php

namespace Tests\Unit\Services\Formatters;

use App\Enums\ContentRenderContext;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\BlogPostContent;
use App\Models\ContentGallery;
use App\Models\ContentMarkdown;
use App\Models\ContentVideo;
use App\Models\Picture;
use App\Models\TranslationKey;
use App\Models\Video;
use App\Services\CustomEmojiResolverService;
use App\Services\Formatters\ContentBlockFormatter;
use App\Services\Formatters\MediaFormatter;
use App\Services\Formatters\TranslationHelper;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ContentBlockFormatter::class)]
class ContentBlockFormatterTest extends TestCase
{
    private ContentBlockFormatter $formatter;

    private MediaFormatter&MockInterface $mediaFormatter;

    private TranslationHelper&MockInterface $translationHelper;

    private CustomEmojiResolverService&MockInterface $emojiResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaFormatter = Mockery::mock(MediaFormatter::class);
        $this->translationHelper = Mockery::mock(TranslationHelper::class);
        $this->emojiResolver = Mockery::mock(CustomEmojiResolverService::class);

        $this->formatter = new ContentBlockFormatter(
            $this->mediaFormatter,
            $this->translationHelper,
            $this->emojiResolver,
        );
    }

    #[Test]
    public function it_formats_markdown_content_block(): void
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $translationKey */
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->translations = $translations;

        /** @var ContentMarkdown&MockInterface $contentMarkdown */
        $contentMarkdown = Mockery::mock(ContentMarkdown::class)->makePartial();
        $contentMarkdown->translationKey = $translationKey;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 1;
        $content->order = 0;
        $content->content_type = ContentMarkdown::class;
        $content->content = $contentMarkdown;

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->with($translations)
            ->andReturn('# Hello World');

        $this->emojiResolver
            ->shouldReceive('resolveEmojisInMarkdown')
            ->with('# Hello World')
            ->andReturn('# Hello World');

        $result = $this->formatter->format($content);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals(0, $result['order']);
        $this->assertEquals(ContentMarkdown::class, $result['content_type']);
        $this->assertEquals('# Hello World', $result['markdown']);
    }

    #[Test]
    public function it_handles_emoji_resolution_failure_gracefully(): void
    {
        $translations = new Collection;

        /** @var TranslationKey&MockInterface $translationKey */
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->translations = $translations;

        /** @var ContentMarkdown&MockInterface $contentMarkdown */
        $contentMarkdown = Mockery::mock(ContentMarkdown::class)->makePartial();
        $contentMarkdown->translationKey = $translationKey;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 1;
        $content->order = 0;
        $content->content_type = ContentMarkdown::class;
        $content->content = $contentMarkdown;

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->andReturn('Text with :emoji:');

        $this->emojiResolver
            ->shouldReceive('resolveEmojisInMarkdown')
            ->andThrow(new Exception('Emoji resolution failed'));

        $result = $this->formatter->format($content);

        $this->assertEquals('Text with :emoji:', $result['markdown']);
    }

    #[Test]
    public function it_formats_markdown_with_null_translation_key(): void
    {
        /** @var ContentMarkdown&MockInterface $contentMarkdown */
        $contentMarkdown = Mockery::mock(ContentMarkdown::class)->makePartial();
        $contentMarkdown->translationKey = null;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 1;
        $content->order = 0;
        $content->content_type = ContentMarkdown::class;
        $content->content = $contentMarkdown;

        $this->emojiResolver
            ->shouldReceive('resolveEmojisInMarkdown')
            ->with('')
            ->andReturn('');

        $result = $this->formatter->format($content);

        $this->assertEquals('', $result['markdown']);
    }

    #[Test]
    public function it_formats_gallery_content_block(): void
    {
        /** @var Picture&MockInterface $picture */
        $picture = Mockery::mock(Picture::class)->makePartial();
        $picture->filename = 'image.jpg';
        $picture->pivot = null;

        $pictures = new Collection([$picture]);

        /** @var ContentGallery&MockInterface $contentGallery */
        $contentGallery = Mockery::mock(ContentGallery::class)->makePartial();
        $contentGallery->id = 42;
        $contentGallery->pictures = $pictures;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 2;
        $content->order = 1;
        $content->content_type = ContentGallery::class;
        $content->content = $contentGallery;

        $this->mediaFormatter
            ->shouldReceive('formatPicture')
            ->with($picture)
            ->andReturn([
                'filename' => 'image.jpg',
                'width' => 800,
                'height' => 600,
                'avif' => [],
                'webp' => [],
                'jpg' => [],
            ]);

        $result = $this->formatter->format($content);

        $this->assertEquals(2, $result['id']);
        $this->assertEquals(1, $result['order']);
        $this->assertEquals(ContentGallery::class, $result['content_type']);
        $this->assertArrayHasKey('gallery', $result);
        $this->assertEquals(42, $result['gallery']['id']);
        $this->assertCount(1, $result['gallery']['pictures']);
    }

    #[Test]
    public function it_formats_video_content_block_when_ready_and_public(): void
    {
        config(['services.bunny.stream_library_id' => 'test-lib']);

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();
        $coverPicture->filename = 'cover.jpg';

        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 10;
        $video->bunny_video_id = 'bunny-123';
        $video->name = 'Test Video';
        $video->status = VideoStatus::READY;
        $video->visibility = VideoVisibility::PUBLIC;
        $video->coverPicture = $coverPicture;

        /** @var ContentVideo&MockInterface $contentVideo */
        $contentVideo = Mockery::mock(ContentVideo::class)->makePartial();
        $contentVideo->video = $video;
        $contentVideo->captionTranslationKey = null;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 3;
        $content->order = 2;
        $content->content_type = ContentVideo::class;
        $content->content = $contentVideo;

        $this->mediaFormatter
            ->shouldReceive('formatVideo')
            ->with($video)
            ->andReturn([
                'id' => 10,
                'bunnyVideoId' => 'bunny-123',
                'name' => 'Test Video',
                'coverPicture' => ['filename' => 'cover.jpg'],
                'libraryId' => 'test-lib',
            ]);

        $result = $this->formatter->format($content);

        $this->assertArrayHasKey('video', $result);
        $this->assertEquals(10, $result['video']['id']);
        $this->assertNull($result['video']['caption']);
    }

    #[Test]
    public function it_does_not_include_video_when_not_ready(): void
    {
        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->status = VideoStatus::TRANSCODING;
        $video->visibility = VideoVisibility::PUBLIC;

        /** @var ContentVideo&MockInterface $contentVideo */
        $contentVideo = Mockery::mock(ContentVideo::class)->makePartial();
        $contentVideo->video = $video;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 3;
        $content->order = 2;
        $content->content_type = ContentVideo::class;
        $content->content = $contentVideo;

        $result = $this->formatter->format($content);

        $this->assertArrayNotHasKey('video', $result);
    }

    #[Test]
    public function it_does_not_include_private_video_in_public_context(): void
    {
        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->status = VideoStatus::READY;
        $video->visibility = VideoVisibility::PRIVATE;

        /** @var ContentVideo&MockInterface $contentVideo */
        $contentVideo = Mockery::mock(ContentVideo::class)->makePartial();
        $contentVideo->video = $video;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 3;
        $content->order = 2;
        $content->content_type = ContentVideo::class;
        $content->content = $contentVideo;

        $result = $this->formatter->format($content, ContentRenderContext::PUBLIC);

        $this->assertArrayNotHasKey('video', $result);
    }

    #[Test]
    public function it_includes_private_video_in_preview_context(): void
    {
        config(['services.bunny.stream_library_id' => 'test-lib']);

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();
        $coverPicture->filename = 'cover.jpg';

        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 10;
        $video->bunny_video_id = 'bunny-123';
        $video->name = 'Private Video';
        $video->status = VideoStatus::READY;
        $video->visibility = VideoVisibility::PRIVATE;
        $video->coverPicture = $coverPicture;

        /** @var ContentVideo&MockInterface $contentVideo */
        $contentVideo = Mockery::mock(ContentVideo::class)->makePartial();
        $contentVideo->video = $video;
        $contentVideo->captionTranslationKey = null;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 3;
        $content->order = 2;
        $content->content_type = ContentVideo::class;
        $content->content = $contentVideo;

        $this->mediaFormatter
            ->shouldReceive('formatVideo')
            ->with($video)
            ->andReturn([
                'id' => 10,
                'bunnyVideoId' => 'bunny-123',
                'name' => 'Private Video',
                'coverPicture' => ['filename' => 'cover.jpg'],
                'libraryId' => 'test-lib',
            ]);

        $result = $this->formatter->format($content, ContentRenderContext::PREVIEW);

        $this->assertArrayHasKey('video', $result);
        $this->assertEquals('Private Video', $result['video']['name']);
    }

    #[Test]
    public function it_formats_video_with_caption(): void
    {
        config(['services.bunny.stream_library_id' => 'test-lib']);

        $translations = new Collection;

        /** @var TranslationKey&MockInterface $captionKey */
        $captionKey = Mockery::mock(TranslationKey::class)->makePartial();
        $captionKey->translations = $translations;

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();
        $coverPicture->filename = 'cover.jpg';

        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 10;
        $video->bunny_video_id = 'bunny-123';
        $video->name = 'Test Video';
        $video->status = VideoStatus::READY;
        $video->visibility = VideoVisibility::PUBLIC;
        $video->coverPicture = $coverPicture;

        /** @var ContentVideo&MockInterface $contentVideo */
        $contentVideo = Mockery::mock(ContentVideo::class)->makePartial();
        $contentVideo->video = $video;
        $contentVideo->captionTranslationKey = $captionKey;

        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 3;
        $content->order = 2;
        $content->content_type = ContentVideo::class;
        $content->content = $contentVideo;

        $this->translationHelper
            ->shouldReceive('getWithFallback')
            ->with($translations)
            ->andReturn('Video caption text');

        $this->mediaFormatter
            ->shouldReceive('formatVideo')
            ->andReturn([
                'id' => 10,
                'bunnyVideoId' => 'bunny-123',
                'name' => 'Test Video',
                'coverPicture' => [],
                'libraryId' => 'test-lib',
            ]);

        $result = $this->formatter->format($content);

        $this->assertEquals('Video caption text', $result['video']['caption']);
    }

    #[Test]
    public function it_returns_base_structure_for_unknown_content_type(): void
    {
        /** @var BlogPostContent&MockInterface $content */
        $content = Mockery::mock(BlogPostContent::class)->makePartial();
        $content->id = 5;
        $content->order = 3;
        $content->content_type = 'UnknownContentType';
        $content->content = null;

        $result = $this->formatter->format($content);

        $this->assertEquals(5, $result['id']);
        $this->assertEquals(3, $result['order']);
        $this->assertEquals('UnknownContentType', $result['content_type']);
        $this->assertArrayNotHasKey('markdown', $result);
        $this->assertArrayNotHasKey('gallery', $result);
        $this->assertArrayNotHasKey('video', $result);
    }
}
