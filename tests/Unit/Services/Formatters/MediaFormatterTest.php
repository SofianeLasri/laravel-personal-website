<?php

namespace Tests\Unit\Services\Formatters;

use App\Models\Picture;
use App\Models\Video;
use App\Services\Formatters\MediaFormatter;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(MediaFormatter::class)]
class MediaFormatterTest extends TestCase
{
    private MediaFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new MediaFormatter;
    }

    #[Test]
    public function it_formats_picture_with_all_properties(): void
    {
        /** @var Picture&MockInterface $picture */
        $picture = Mockery::mock(Picture::class)->makePartial();
        $picture->filename = 'test-image.jpg';
        $picture->width = 1920;
        $picture->height = 1080;

        $picture->shouldReceive('getUrl')
            ->with('thumbnail', 'avif')
            ->andReturn('https://cdn.example.com/test-image_thumbnail.avif');
        $picture->shouldReceive('getUrl')
            ->with('small', 'avif')
            ->andReturn('https://cdn.example.com/test-image_small.avif');
        $picture->shouldReceive('getUrl')
            ->with('medium', 'avif')
            ->andReturn('https://cdn.example.com/test-image_medium.avif');
        $picture->shouldReceive('getUrl')
            ->with('large', 'avif')
            ->andReturn('https://cdn.example.com/test-image_large.avif');
        $picture->shouldReceive('getUrl')
            ->with('full', 'avif')
            ->andReturn('https://cdn.example.com/test-image_full.avif');

        $picture->shouldReceive('getUrl')
            ->with('thumbnail', 'webp')
            ->andReturn('https://cdn.example.com/test-image_thumbnail.webp');
        $picture->shouldReceive('getUrl')
            ->with('small', 'webp')
            ->andReturn('https://cdn.example.com/test-image_small.webp');
        $picture->shouldReceive('getUrl')
            ->with('medium', 'webp')
            ->andReturn('https://cdn.example.com/test-image_medium.webp');
        $picture->shouldReceive('getUrl')
            ->with('large', 'webp')
            ->andReturn('https://cdn.example.com/test-image_large.webp');
        $picture->shouldReceive('getUrl')
            ->with('full', 'webp')
            ->andReturn('https://cdn.example.com/test-image_full.webp');

        $picture->shouldReceive('getUrl')
            ->with('thumbnail', 'jpg')
            ->andReturn('https://cdn.example.com/test-image_thumbnail.jpg');
        $picture->shouldReceive('getUrl')
            ->with('small', 'jpg')
            ->andReturn('https://cdn.example.com/test-image_small.jpg');
        $picture->shouldReceive('getUrl')
            ->with('medium', 'jpg')
            ->andReturn('https://cdn.example.com/test-image_medium.jpg');
        $picture->shouldReceive('getUrl')
            ->with('large', 'jpg')
            ->andReturn('https://cdn.example.com/test-image_large.jpg');
        $picture->shouldReceive('getUrl')
            ->with('full', 'jpg')
            ->andReturn('https://cdn.example.com/test-image_full.jpg');

        $result = $this->formatter->formatPicture($picture);

        $this->assertEquals('test-image.jpg', $result['filename']);
        $this->assertEquals(1920, $result['width']);
        $this->assertEquals(1080, $result['height']);

        // Check AVIF variants
        $this->assertEquals('https://cdn.example.com/test-image_thumbnail.avif', $result['avif']['thumbnail']);
        $this->assertEquals('https://cdn.example.com/test-image_small.avif', $result['avif']['small']);
        $this->assertEquals('https://cdn.example.com/test-image_medium.avif', $result['avif']['medium']);
        $this->assertEquals('https://cdn.example.com/test-image_large.avif', $result['avif']['large']);
        $this->assertEquals('https://cdn.example.com/test-image_full.avif', $result['avif']['full']);

        // Check WebP variants
        $this->assertEquals('https://cdn.example.com/test-image_thumbnail.webp', $result['webp']['thumbnail']);
        $this->assertEquals('https://cdn.example.com/test-image_small.webp', $result['webp']['small']);
        $this->assertEquals('https://cdn.example.com/test-image_medium.webp', $result['webp']['medium']);
        $this->assertEquals('https://cdn.example.com/test-image_large.webp', $result['webp']['large']);
        $this->assertEquals('https://cdn.example.com/test-image_full.webp', $result['webp']['full']);

        // Check JPG variants
        $this->assertEquals('https://cdn.example.com/test-image_thumbnail.jpg', $result['jpg']['thumbnail']);
        $this->assertEquals('https://cdn.example.com/test-image_small.jpg', $result['jpg']['small']);
        $this->assertEquals('https://cdn.example.com/test-image_medium.jpg', $result['jpg']['medium']);
        $this->assertEquals('https://cdn.example.com/test-image_large.jpg', $result['jpg']['large']);
        $this->assertEquals('https://cdn.example.com/test-image_full.jpg', $result['jpg']['full']);
    }

    #[Test]
    public function it_formats_picture_with_null_dimensions(): void
    {
        /** @var Picture&MockInterface $picture */
        $picture = Mockery::mock(Picture::class)->makePartial();
        $picture->filename = 'unprocessed.jpg';
        $picture->width = null;
        $picture->height = null;

        $picture->shouldReceive('getUrl')->andReturn('');

        $result = $this->formatter->formatPicture($picture);

        $this->assertEquals('unprocessed.jpg', $result['filename']);
        $this->assertNull($result['width']);
        $this->assertNull($result['height']);
    }

    #[Test]
    public function it_returns_correct_array_structure_for_picture(): void
    {
        /** @var Picture&MockInterface $picture */
        $picture = Mockery::mock(Picture::class)->makePartial();
        $picture->filename = 'test.jpg';
        $picture->width = 800;
        $picture->height = 600;

        $picture->shouldReceive('getUrl')->andReturn('');

        $result = $this->formatter->formatPicture($picture);

        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertArrayHasKey('avif', $result);
        $this->assertArrayHasKey('webp', $result);
        $this->assertArrayHasKey('jpg', $result);

        // Verify variant structure
        foreach (['avif', 'webp', 'jpg'] as $format) {
            $this->assertArrayHasKey('thumbnail', $result[$format]);
            $this->assertArrayHasKey('small', $result[$format]);
            $this->assertArrayHasKey('medium', $result[$format]);
            $this->assertArrayHasKey('large', $result[$format]);
            $this->assertArrayHasKey('full', $result[$format]);
        }
    }

    #[Test]
    public function it_formats_video_with_all_properties(): void
    {
        config(['services.bunny.stream_library_id' => 'test-library-123']);

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();
        $coverPicture->filename = 'cover.jpg';
        $coverPicture->width = 1920;
        $coverPicture->height = 1080;
        $coverPicture->shouldReceive('getUrl')->andReturn('https://cdn.example.com/cover.jpg');

        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 42;
        $video->bunny_video_id = 'abc-123-def';
        $video->name = 'Test Video';
        $video->coverPicture = $coverPicture;

        $result = $this->formatter->formatVideo($video);

        $this->assertEquals(42, $result['id']);
        $this->assertEquals('abc-123-def', $result['bunnyVideoId']);
        $this->assertEquals('Test Video', $result['name']);
        $this->assertEquals('test-library-123', $result['libraryId']);
        $this->assertArrayHasKey('coverPicture', $result);
        $this->assertEquals('cover.jpg', $result['coverPicture']['filename']);
    }

    #[Test]
    public function it_returns_correct_array_structure_for_video(): void
    {
        config(['services.bunny.stream_library_id' => 'lib-id']);

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();
        $coverPicture->filename = 'cover.jpg';
        $coverPicture->width = 1280;
        $coverPicture->height = 720;
        $coverPicture->shouldReceive('getUrl')->andReturn('');

        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;
        $video->bunny_video_id = 'bunny-id';
        $video->name = 'Video Name';
        $video->coverPicture = $coverPicture;

        $result = $this->formatter->formatVideo($video);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('bunnyVideoId', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('coverPicture', $result);
        $this->assertArrayHasKey('libraryId', $result);
    }

    #[Test]
    public function it_uses_bunny_library_id_from_config(): void
    {
        config(['services.bunny.stream_library_id' => 'custom-library-id']);

        /** @var Picture&MockInterface $coverPicture */
        $coverPicture = Mockery::mock(Picture::class)->makePartial();
        $coverPicture->filename = 'cover.jpg';
        $coverPicture->width = 1920;
        $coverPicture->height = 1080;
        $coverPicture->shouldReceive('getUrl')->andReturn('');

        /** @var Video&MockInterface $video */
        $video = Mockery::mock(Video::class)->makePartial();
        $video->id = 1;
        $video->bunny_video_id = 'video-id';
        $video->name = 'Test';
        $video->coverPicture = $coverPicture;

        $result = $this->formatter->formatVideo($video);

        $this->assertEquals('custom-library-id', $result['libraryId']);
    }
}
