<?php

namespace Tests\Feature\Models\Video;

use App\Http\Requests\VideoRequest;
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
            'filename' => 'test-video.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes(), 'La validation devrait réussir avec des données valides.');
    }

    #[Test]
    public function it_fails_when_filename_is_missing(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand le filename est manquant.');
        $this->assertArrayHasKey('filename', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_filename_is_empty(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'filename' => '',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer quand le filename est vide.');
        $this->assertArrayHasKey('filename', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_cover_picture_id_is_missing(): void
    {
        $data = [
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

    #[Test]
    public function it_passes_with_string_filename(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'filename' => 'my-awesome-video-file.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'bunny-xyz789',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes(), 'La validation devrait réussir avec un filename string valide.');
    }

    #[Test]
    public function it_passes_with_string_bunny_video_id(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'filename' => 'test.mp4',
            'cover_picture_id' => $picture->id,
            'bunny_video_id' => 'very-long-bunny-video-identifier-12345',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes(), 'La validation devrait réussir avec un bunny_video_id string valide.');
    }
}
