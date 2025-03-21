<?php

namespace Tests\Feature\Models\Creation;

use App\Enums\CreationType;
use App\Http\Requests\CreationDraftRequest;
use App\Models\Creation;
use App\Models\Person;
use App\Models\Picture;
use App\Models\Tag;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreationDraftRequest::class)]
class CreationDraftRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new CreationDraftRequest)->rules();
    }

    protected function validData(array $overrides = []): array
    {
        $types = CreationType::values();
        $validType = $types[0] ?? 'default';

        $logo = Picture::factory()->create();
        $cover = Picture::factory()->create();

        $originalCreation = Creation::factory()->create();

        $people = Person::factory()->count(2)->create()->pluck('id')->toArray();
        $technologies = Technology::factory()->count(2)->create()->pluck('id')->toArray();
        $tags = Tag::factory()->count(2)->create()->pluck('id')->toArray();

        $data = [
            'locale' => 'en',
            'name' => 'Nom du brouillon',
            'slug' => 'nom-du-brouillon',
            'logo_id' => $logo->id,
            'cover_image_id' => $cover->id,
            'type' => $validType,
            'started_at' => now()->toDateString(),
            'ended_at' => now()->addDays(10)->toDateString(),
            'short_description_content' => 'Contenu court de description',
            'full_description_content' => 'Contenu complet de description',
            'external_url' => 'https://example.com',
            'source_code_url' => 'https://github.com/example',
            'original_creation_id' => $originalCreation->id,
            'people' => $people,
            'technologies' => $technologies,
            'tags' => $tags,
        ];

        return array_merge($data, $overrides);
    }

    #[Test]
    public function it_passes_with_valid_data(): void
    {
        $data = $this->validData();

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_when_required_fields_are_missing(): void
    {
        $data = $this->validData([
            'locale' => null,
            'name' => null,
            'slug' => null,
            'type' => null,
            'started_at' => null,
            'short_description_content' => null,
            'full_description_content' => null,
        ]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('locale', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('slug', $errors);
        $this->assertArrayHasKey('type', $errors);
        $this->assertArrayHasKey('started_at', $errors);
        $this->assertArrayHasKey('short_description_content', $errors);
        $this->assertArrayHasKey('full_description_content', $errors);
    }

    #[Test]
    public function it_fails_when_locale_is_invalid(): void
    {
        $data = $this->validData(['locale' => 'de']);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_name_exceeds_max_length(): void
    {
        $data = $this->validData(['name' => str_repeat('a', 256)]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_slug_exceeds_max_length(): void
    {
        $data = $this->validData(['slug' => str_repeat('a', 256)]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('slug', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_type_is_invalid(): void
    {
        $data = $this->validData(['type' => 'invalid-type']);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('type', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_started_at_is_not_a_date(): void
    {
        $data = $this->validData(['started_at' => 'not-a-date']);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('started_at', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_ended_at_is_not_a_date_if_provided(): void
    {
        $data = $this->validData(['ended_at' => 'not-a-date']);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('ended_at', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_external_url_is_invalid(): void
    {
        $data = $this->validData(['external_url' => 'invalid-url']);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('external_url', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_source_code_url_is_invalid(): void
    {
        $data = $this->validData(['source_code_url' => 'invalid-url']);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('source_code_url', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_logo_id_does_not_exist(): void
    {
        $data = $this->validData(['logo_id' => 9999]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('logo_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_cover_image_id_does_not_exist(): void
    {
        $data = $this->validData(['cover_image_id' => 9999]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('cover_image_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_original_creation_id_does_not_exist(): void
    {
        $data = $this->validData(['original_creation_id' => 9999]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('original_creation_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_people_array_contains_invalid_ids(): void
    {
        $data = $this->validData(['people' => [1, 9999]]);
        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('people.1', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_technologies_array_contains_invalid_ids(): void
    {
        $data = $this->validData(['technologies' => [1, 9999]]);
        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('technologies.1', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_tags_array_contains_invalid_ids(): void
    {
        $data = $this->validData(['tags' => [1, 9999]]);
        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('tags.1', $validator->errors()->toArray());
    }
}
