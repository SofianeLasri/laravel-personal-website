<?php

namespace Tests\Feature\Models\Certification;

use App\Http\Requests\CertificationRequest;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CertificationRequest::class)]
class CertificationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new CertificationRequest)->rules();
    }

    #[Test]
    public function it_passes_with_valid_data()
    {
        Picture::factory()->create();

        $data = [
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'date' => '2024-05-15',
            'link' => 'https://laravel.com/certification',
            'picture_id' => 1,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_fails_when_name_is_missing()
    {
        Picture::factory()->create();

        $data = [
            'score' => '850/1000',
            'date' => '2024-05-15',
            'link' => 'https://laravel.com/certification',
            'picture_id' => 1,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('name'));
    }

    #[Test]
    public function it_fails_when_score_is_missing()
    {
        Picture::factory()->create();

        $data = [
            'name' => 'Laravel Certified Developer',
            'date' => '2024-05-15',
            'link' => 'https://laravel.com/certification',
            'picture_id' => 1,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('score'));
    }

    #[Test]
    public function it_fails_when_date_is_missing()
    {
        Picture::factory()->create();

        $data = [
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'link' => 'https://laravel.com/certification',
            'picture_id' => 1,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('date'));
    }

    #[Test]
    public function it_fails_when_date_is_invalid()
    {
        Picture::factory()->create();

        $data = [
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'date' => 'invalid-date',
            'link' => 'https://laravel.com/certification',
            'picture_id' => 1,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('date'));
    }

    #[Test]
    public function it_fails_when_link_is_missing()
    {
        Picture::factory()->create();

        $data = [
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'date' => '2024-05-15',
            'picture_id' => 1,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('link'));
    }

    #[Test]
    public function it_fails_when_picture_id_is_missing()
    {
        $data = [
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'date' => '2024-05-15',
            'link' => 'https://laravel.com/certification',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('picture_id'));
    }

    #[Test]
    public function it_fails_when_picture_id_does_not_exist()
    {
        $data = [
            'name' => 'Laravel Certified Developer',
            'score' => '850/1000',
            'date' => '2024-05-15',
            'link' => 'https://laravel.com/certification',
            'picture_id' => 999999,
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('picture_id'));
    }

    #[Test]
    public function it_has_authorize_method_returning_true()
    {
        $request = new CertificationRequest;
        $this->assertTrue($request->authorize());
    }
}
