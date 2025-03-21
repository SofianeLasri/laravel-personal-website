<?php

namespace Tests\Feature\Models\Person;

use App\Http\Requests\PersonRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PersonRequest::class)]
class PersonRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new PersonRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data_with_picture(): void
    {
        $picture = Picture::factory()->create();

        $data = [
            'name' => 'John Doe',
            'picture_id' => $picture->id,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_passes_with_valid_data_without_picture(): void
    {
        $data = [
            'name' => 'John Doe',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_when_name_is_missing(): void
    {
        $data = [
            'picture_id' => null,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_picture_id_does_not_exist(): void
    {
        $data = [
            'name' => 'John Doe',
            'picture_id' => 9999,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('picture_id', $validator->errors()->toArray());
    }
}
