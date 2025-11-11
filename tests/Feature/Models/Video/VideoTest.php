<?php

namespace Tests\Feature\Models\Video;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\ContentVideo;
use App\Models\Creation;
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
            'status',
            'visibility',
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
        $video = Video::factory()->readyAndPublic()->create();
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

    #[Test]
    public function test_video_belongs_to_many_creations()
    {
        $video = Video::factory()->create();
        $creation1 = Creation::factory()->create();
        $creation2 = Creation::factory()->create();

        $video->creations()->attach([$creation1->id, $creation2->id]);

        $this->assertCount(2, $video->creations);
        $this->assertTrue($video->creations->contains($creation1));
        $this->assertTrue($video->creations->contains($creation2));
    }

    #[Test]
    public function test_video_has_many_blog_content_videos()
    {
        $video = Video::factory()->create();
        $blogContentVideo1 = ContentVideo::factory()->create(['video_id' => $video->id]);
        $blogContentVideo2 = ContentVideo::factory()->create(['video_id' => $video->id]);

        $this->assertCount(2, $video->blogContentVideos);
        $this->assertTrue($video->blogContentVideos->contains($blogContentVideo1));
        $this->assertTrue($video->blogContentVideos->contains($blogContentVideo2));
    }

    #[Test]
    public function test_video_creations_relationship_can_be_loaded()
    {
        $video = Video::factory()->create();
        $creation = Creation::factory()->create();
        $video->creations()->attach($creation->id);

        $loadedVideo = Video::with('creations')->find($video->id);

        $this->assertTrue($loadedVideo->relationLoaded('creations'));
        $this->assertCount(1, $loadedVideo->creations);
    }

    #[Test]
    public function test_video_blog_content_videos_relationship_can_be_loaded()
    {
        $video = Video::factory()->create();
        ContentVideo::factory()->create(['video_id' => $video->id]);

        $loadedVideo = Video::with('blogContentVideos')->find($video->id);

        $this->assertTrue($loadedVideo->relationLoaded('blogContentVideos'));
        $this->assertCount(1, $loadedVideo->blogContentVideos);
    }

    #[Test]
    public function test_video_status_is_cast_to_enum()
    {
        $video = Video::factory()->create(['status' => VideoStatus::READY]);

        $this->assertInstanceOf(VideoStatus::class, $video->status);
        $this->assertEquals(VideoStatus::READY, $video->status);
    }

    #[Test]
    public function test_video_visibility_is_cast_to_enum()
    {
        $video = Video::factory()->create(['visibility' => VideoVisibility::PUBLIC]);

        $this->assertInstanceOf(VideoVisibility::class, $video->visibility);
        $this->assertEquals(VideoVisibility::PUBLIC, $video->visibility);
    }

    #[Test]
    public function test_video_can_have_all_status_values()
    {
        foreach (VideoStatus::cases() as $status) {
            $video = Video::factory()->create(['status' => $status]);

            $this->assertEquals($status, $video->status);
            $this->assertDatabaseHas('videos', [
                'id' => $video->id,
                'status' => $status->value,
            ]);
        }
    }

    #[Test]
    public function test_video_can_have_all_visibility_values()
    {
        foreach (VideoVisibility::cases() as $visibility) {
            $video = Video::factory()->create(['visibility' => $visibility]);

            $this->assertEquals($visibility, $video->visibility);
            $this->assertDatabaseHas('videos', [
                'id' => $video->id,
                'visibility' => $visibility->value,
            ]);
        }
    }

    #[Test]
    public function test_video_casts_are_defined()
    {
        $video = new Video;
        $casts = $video->getCasts();

        $this->assertArrayHasKey('status', $casts);
        $this->assertArrayHasKey('visibility', $casts);
        $this->assertEquals(VideoStatus::class, $casts['status']);
        $this->assertEquals(VideoVisibility::class, $casts['visibility']);
    }

    #[Test]
    public function test_video_factory_ready_and_public_state()
    {
        $video = Video::factory()->readyAndPublic()->create();

        $this->assertEquals(VideoStatus::READY, $video->status);
        $this->assertEquals(VideoVisibility::PUBLIC, $video->visibility);
        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'status' => VideoStatus::READY->value,
            'visibility' => VideoVisibility::PUBLIC->value,
        ]);
    }

    #[Test]
    public function test_video_factory_transcoding_and_private_state()
    {
        $video = Video::factory()->transcodingAndPrivate()->create();

        $this->assertEquals(VideoStatus::TRANSCODING, $video->status);
        $this->assertEquals(VideoVisibility::PRIVATE, $video->visibility);
        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'status' => VideoStatus::TRANSCODING->value,
            'visibility' => VideoVisibility::PRIVATE->value,
        ]);
    }

    #[Test]
    public function test_video_can_be_created_without_cover_picture()
    {
        $video = Video::factory()->create(['cover_picture_id' => null]);

        $this->assertNull($video->cover_picture_id);
        $this->assertNull($video->coverPicture);
        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'cover_picture_id' => null,
        ]);
    }

    #[Test]
    public function test_video_cover_picture_relationship_with_null()
    {
        $video = Video::factory()->create(['cover_picture_id' => null]);

        $loadedVideo = Video::with('coverPicture')->find($video->id);

        $this->assertTrue($loadedVideo->relationLoaded('coverPicture'));
        $this->assertNull($loadedVideo->coverPicture);
    }

    #[Test]
    public function test_video_status_enum_values_method()
    {
        $values = VideoStatus::values();
        $expectedValues = ['pending', 'transcoding', 'ready', 'error'];

        $this->assertEquals($expectedValues, $values);
    }

    #[Test]
    public function test_video_visibility_enum_values_method()
    {
        $values = VideoVisibility::values();
        $expectedValues = ['public', 'private'];

        $this->assertEquals($expectedValues, $values);
    }

    #[Test]
    public function test_video_timestamps_are_updated_on_modification()
    {
        $video = Video::factory()->create();
        $originalUpdatedAt = $video->updated_at;

        sleep(1);
        $video->update(['name' => 'Updated name']);

        $this->assertNotEquals($originalUpdatedAt, $video->fresh()->updated_at);
        $this->assertTrue($video->fresh()->updated_at->greaterThan($originalUpdatedAt));
    }

    #[Test]
    public function test_video_model_uses_correct_table()
    {
        $video = new Video;

        $this->assertEquals('videos', $video->getTable());
    }

    #[Test]
    public function test_video_primary_key_is_id()
    {
        $video = new Video;

        $this->assertEquals('id', $video->getKeyName());
    }

    #[Test]
    public function test_video_uses_timestamps()
    {
        $video = new Video;

        $this->assertTrue($video->usesTimestamps());
    }
}
