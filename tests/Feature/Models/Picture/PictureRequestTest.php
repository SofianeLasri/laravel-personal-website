<?php

namespace Tests\Feature\Models\Picture;

use App\Http\Requests\PictureRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

#[CoversClass(PictureRequest::class)]
class PictureRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new PictureRequest)->rules();
    }

    protected function setImageConfig(int $maxWidth = 1920, int $maxHeight = 1080): void
    {
        config([
            'app.imagick.max_width' => $maxWidth,
            'app.imagick.max_height' => $maxHeight,
        ]);
    }

    #[Test]
    public function it_passes_with_a_valid_image(): void
    {
        $this->setImageConfig();

        $file = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1000); // taille en Ko

        $data = ['picture' => $file];

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes(), 'La validation devrait réussir avec une image valide.');
    }

    #[Test]
    public function it_fails_when_the_file_is_not_an_image(): void
    {
        $this->setImageConfig();

        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $data = ['picture' => $file];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si le fichier n\'est pas une image.');
        $this->assertArrayHasKey('picture', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_image_dimensions_exceed_the_maximum(): void
    {
        $this->setImageConfig();

        $file = UploadedFile::fake()->image('test_large.jpg', 2000, 1200);

        $data = ['picture' => $file];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si les dimensions de l\'image excèdent les limites.');
        $this->assertArrayHasKey('picture', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_image_file_size_exceeds_the_limit(): void
    {
        $this->setImageConfig();

        $file = UploadedFile::fake()->image('test_big.jpg', 800, 600)->size(51201);

        $data = ['picture' => $file];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si la taille du fichier dépasse 50mb.');
        $this->assertArrayHasKey('picture', $validator->errors()->toArray());
    }

    #[TestDox('All the supported image formats are valid')]
    public function test_it_passes_with_supported_image_formats(): void
    {
        $this->setImageConfig();

        $supportedFormats = config('app.supported_image_formats');

        foreach ($supportedFormats as $format) {
            $file = UploadedFile::fake()->image("test.$format");

            $data = ['picture' => $file];

            $validator = Validator::make($data, $this->rules());
            $this->assertTrue($validator->passes(), "La validation devrait réussir avec le format d'image: $format.");
        }
    }
}
