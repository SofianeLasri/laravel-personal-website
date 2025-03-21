<?php

namespace Tests\Feature\Models\Creation;

use App\Http\Requests\Feature\UpdateCreationDraftFeatureRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UpdateCreationDraftFeatureRequest::class)]
class UpdateCreationDraftFeatureRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new UpdateCreationDraftFeatureRequest)->rules();
    }

    #[Test]
    public function it_passes_when_no_fields_are_present(): void
    {
        $data = [];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_with_valid_data_when_title_and_description_are_provided(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Un titre valide',
            'description' => 'Une description valide',
            'picture_id' => $picture->id,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_when_locale_is_missing_with_title(): void
    {
        $data = [
            'title' => 'Titre présent',
            'description' => 'Description présente',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_locale_is_missing_with_description_only(): void
    {
        $data = [
            'description' => 'Description présente',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_with_invalid_locale(): void
    {
        $data = [
            'locale' => 'de',
            'title' => 'Titre',
            'description' => 'Description',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_picture_id_does_not_exist(): void
    {
        $data = [
            'locale' => 'fr',
            'title' => 'Titre',
            'description' => 'Description',
            'picture_id' => 9999,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('picture_id', $validator->errors()->toArray());
    }
}
