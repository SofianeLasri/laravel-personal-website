<?php

namespace Tests\Feature\Models\Video;

use App\Http\Requests\Video\VideoRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(VideoRequest::class)]
class VideoRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new VideoRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'name' => 'Test Video',
            'path' => 'videos/test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes(), 'La validation devrait réussir avec des données valides.');
    }

    #[Test]
    public function it_fails_when_path_name_is_missing(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand le path est manquant.');
        $this->assertArrayHasKey('path', $validator->errors()->toArray());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_name_is_empty(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'name' => '',
            'path' => 'videos/test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand le name est vide.');
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_cover_picture_id_is_missing(): void
    {
        $data = [
            'path' => 'videos/test-video.mp4',
            'filename' => 'test-video.mp4',
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand cover_picture_id est manquant.');
        $this->assertArrayHasKey('cover_picture_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_cover_picture_id_does_not_exist(): void
    {
        $data = [
            'filename' => 'test-video.mp4',
            'cover_picture_id' => 99999,
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand cover_picture_id n\'existe pas.');
        $this->assertArrayHasKey('cover_picture_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_bunny_video_id_is_missing(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'filename' => 'test-video.mp4',
            'cover_picture_id' => $picture->id,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand bunny_video_id est manquant.');
        $this->assertArrayHasKey('bunny_video_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_bunny_video_id_is_empty(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'filename' => 'test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => '',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand bunny_video_id est vide.');
        $this->assertArrayHasKey('bunny_video_id', $validator->errors()->toArray());
    }

    #[Test]
    public function test_authorize_returns_true(): void
    {
        $request = new VideoRequest;
        $this->assertTrue($request->authorize(), 'La méthode authorize devrait retourner true.');
    }
}
