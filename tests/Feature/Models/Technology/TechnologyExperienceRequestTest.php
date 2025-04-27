<?php

namespace Tests\Feature\Models\Technology;

use App\Http\Requests\TechnologyExperienceRequest;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TechnologyExperienceRequest::class)]
class TechnologyExperienceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new TechnologyExperienceRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data()
    {
        Technology::factory()->create();
        $data = [
            'technology_id' => 1,
            'locale' => 'en',
            'description' => 'Career description',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_when_technology_id_is_missing()
    {
        $data = [
            'locale' => 'en',
            'description' => 'Career description',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
    }
}
