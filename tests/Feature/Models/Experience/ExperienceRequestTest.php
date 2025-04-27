<?php

namespace Tests\Feature\Models\Experience;

use App\Http\Requests\ExperienceRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ExperienceRequest::class)]
class ExperienceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new ExperienceRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data()
    {
        Picture::factory()->create();

        $data = [
            'locale' => 'en',
            'title' => 'Career Title',
            'organization_name' => 'Organization Name',
            'logo_id' => 1,
            'type' => 'emploi',
            'location' => 'Location',
            'website_url' => 'https://example.com',
            'short_description' => 'Short description',
            'full_description' => 'Full description',
            'started_at' => now()->subYear(),
            'ended_at' => now(),
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }
}
