<?php

namespace Tests\Feature\Models\Video;

use App\Models\Picture;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Video::class)]
class VideoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_video_factory_creates_valid_video()
    {
        $video = Video::factory()->create();

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'name' => $video->name,
            'path' => $video->path,
            'bunny_video_id' => $video->bunny_video_id,
        ]);

        $this->assertNotNull($video->name);
        $this->assertNotNull($video->path);
        $this->assertNotNull($video->bunny_video_id);
        $this->assertNotNull($video->cover_picture_id);
    }

    #[Test]
    public function test_video_belongs_to_cover_picture()
    {
        $picture = Picture::factory()->create();
        $video = Video::factory()->create([
            'cover_picture_id' => $picture->id,
        ]);

        $this->assertInstanceOf(Picture::class, $video->coverPicture);
        $this->assertEquals($picture->id, $video->coverPicture->id);
    }

    #[Test]
    public function test_video_has_fillable_attributes()
    {
        $expectedFillable = [
            'name',
            'path',
            'cover_picture_id',
            'bunny_video_id',
        ];

        $video = new Video;

        $this->assertEquals($expectedFillable, $video->getFillable());
    }

    #[Test]
    public function test_video_can_be_created_with_all_attributes()
    {
        $picture = Picture::factory()->create();

        $videoData = [
            'name' => 'Test video',
            'path' => 'videos/test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $video = Video::create($videoData);

        $this->assertDatabaseHas('videos', $videoData);
        $this->assertEquals($videoData['name'], $video->name);
        $this->assertEquals($videoData['path'], $video->path);
        $this->assertEquals($videoData['bunny_video_id'], $video->bunny_video_id);
        $this->assertEquals($videoData['cover_picture_id'], $video->cover_picture_id);
    }

    #[Test]
    public function test_video_can_be_updated()
    {
        $video = Video::factory()->create();
        $newPicture = Picture::factory()->create();

        $updateData = [
            'name' => 'Updated video name',
            'path' => 'videos/updated-video.mp4',
            'bunny_video_id' => 'updated-bunny-12345',
            'cover_picture_id' => $newPicture->id,
        ];

        $video->update($updateData);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            ...$updateData,
        ]);
    }

    #[Test]
    public function test_video_can_be_deleted()
    {
        $video = Video::factory()->create();

        $video->delete();

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    #[Test]
    public function test_video_timestamps_are_set()
    {
        $video = Video::factory()->create();

        $this->assertNotNull($video->created_at);
        $this->assertNotNull($video->updated_at);
    }

    #[Test]
    public function test_video_cover_picture_relationship_can_be_loaded()
    {
        $video = Video::factory()->create();

        $loadedVideo = Video::with('coverPicture')->find($video->id);

        $this->assertTrue($loadedVideo->relationLoaded('coverPicture'));
        $this->assertInstanceOf(Picture::class, $loadedVideo->coverPicture);
    }
}
