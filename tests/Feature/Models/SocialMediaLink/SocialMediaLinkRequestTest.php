<?php

namespace Models\SocialMediaLink;

use App\Http\Requests\SocialMediaLinkRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SocialMediaLinkRequest::class)]
class SocialMediaLinkRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new SocialMediaLinkRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data()
    {
        $data = [
            'icon_svg' => 'test-icon',
            'name' => 'Test Link',
            'url' => 'https://example.com',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }
}
