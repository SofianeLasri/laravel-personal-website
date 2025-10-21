<?php

namespace Tests\Unit\Requests\Video;

use App\Enums\VideoVisibility;
use App\Http\Requests\Video\VideoUpdateRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(VideoUpdateRequest::class)]
class VideoUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_validation_passes_with_valid_data(): void
    {
        $picture = Picture::factory()->create();

        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => 'Valid Video Name',
            'cover_picture_id' => $picture->id,
            'visibility' => VideoVisibility::PUBLIC->value,
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_fails_with_invalid_cover_picture_id(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => 'Valid Video Name',
            'cover_picture_id' => 99999, // Non-existent picture ID
            'visibility' => VideoVisibility::PRIVATE->value,
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('cover_picture_id'));
    }

    #[Test]
    public function test_validation_fails_with_name_exceeding_max_length(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => str_repeat('a', 256), // 256 characters, exceeds max of 255
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    #[Test]
    public function test_validation_fails_with_invalid_visibility(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => 'Valid Video Name',
            'visibility' => 'invalid_visibility', // Invalid enum value
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('visibility'));
    }

    #[Test]
    public function test_validation_passes_with_all_fields_optional(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([]); // No fields provided

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_authorization_always_returns_true(): void
    {
        $request = new VideoUpdateRequest;

        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function test_validation_passes_with_null_cover_picture_id(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => 'Video Name',
            'cover_picture_id' => null,
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_passes_with_only_name(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => 'Just a name',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_passes_with_only_cover_picture_id(): void
    {
        $picture = Picture::factory()->create();

        $request = new VideoUpdateRequest;
        $request->merge([
            'cover_picture_id' => $picture->id,
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_passes_with_only_visibility(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'visibility' => VideoVisibility::PRIVATE->value,
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_passes_with_valid_public_visibility(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'visibility' => 'public',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_passes_with_valid_private_visibility(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'visibility' => 'private',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_passes_with_name_at_max_length(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => str_repeat('a', 255), // Exactly 255 characters
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function test_validation_fails_with_name_as_number(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'name' => 12345,
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    #[Test]
    public function test_validation_fails_with_cover_picture_id_as_string(): void
    {
        $request = new VideoUpdateRequest;
        $request->merge([
            'cover_picture_id' => 'not-a-number',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('cover_picture_id'));
    }
}
