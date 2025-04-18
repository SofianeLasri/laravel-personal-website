<?php

namespace Tests\Feature\Models\Screenshot;

use App\Http\Requests\Screenshot\UpdateCreationDraftScreenshotRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UpdateCreationDraftScreenshotRequest::class)]
class UpdateCreationDraftScreenshotRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new UpdateCreationDraftScreenshotRequest)->rules();
    }

    #[Test]
    public function it_passes_when_no_fields_are_present(): void
    {
        $data = [];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_with_valid_data_with_caption_and_locale(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'caption' => 'Une légende correcte',
            'picture_id' => $picture->id,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_with_valid_data_with_picture_id_only(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'picture_id' => $picture->id,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_when_caption_is_provided_without_locale(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'picture_id' => $picture->id,
            'caption' => 'Légende présente',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_with_invalid_locale_with_caption(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'locale' => 'de',
            'caption' => 'Légende présente',
            'picture_id' => $picture->id,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }
}
