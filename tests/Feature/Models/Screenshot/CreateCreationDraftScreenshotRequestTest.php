<?php

namespace Tests\Feature\Models\Screenshot;

use App\Http\Requests\Screenshot\CreateCreationDraftScreenshotRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreateCreationDraftScreenshotRequest::class)]
class CreateCreationDraftScreenshotRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new CreateCreationDraftScreenshotRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data_with_caption(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'picture_id' => $picture->id,
            'caption' => 'Une légende valide',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_with_valid_data_without_caption(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'picture_id' => $picture->id,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_when_picture_id_is_missing(): void
    {
        $data = [
            'locale' => 'en',
            'caption' => 'Légende présente',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('picture_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_picture_id_does_not_exist(): void
    {
        $data = [
            'locale' => 'en',
            'picture_id' => 9999,
            'caption' => 'Légende présente',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('picture_id', $validator->errors()->toArray());
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
            'picture_id' => $picture->id,
            'caption' => 'Légende présente',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }
}
