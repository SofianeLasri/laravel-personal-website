<?php

namespace Tests\Feature\Models\Creation;

use App\Http\Requests\Feature\CreateCreationDraftFeatureRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreateCreationDraftFeatureRequest::class)]
class CreateCreationDraftFeatureRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new CreateCreationDraftFeatureRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data(): void
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
    public function it_passes_without_optional_picture_id(): void
    {
        $data = [
            'locale' => 'fr',
            'title' => 'Titre sans picture_id',
            'description' => 'Description sans picture_id',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function locale_is_required(): void
    {
        $data = [
            'title' => 'Titre',
            'description' => 'Description',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function locale_must_be_in_defined_list(): void
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
    public function title_is_required(): void
    {
        $data = [
            'locale' => 'en',
            'description' => 'Description',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('title', $validator->errors()->toArray());
    }

    #[Test]
    public function description_is_required(): void
    {
        $data = [
            'locale' => 'fr',
            'title' => 'Titre',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('description', $validator->errors()->toArray());
    }

    #[Test]
    public function picture_id_must_exist_in_the_pictures_table_if_provided(): void
    {
        $invalidPictureId = 9999;

        $data = [
            'locale' => 'en',
            'title' => 'Titre',
            'description' => 'Description',
            'picture_id' => $invalidPictureId,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('picture_id', $validator->errors()->toArray());
    }
}
