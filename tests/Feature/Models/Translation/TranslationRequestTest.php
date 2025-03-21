<?php

namespace Tests\Feature\Models\Translation;

use App\Http\Requests\TranslationRequest;
use App\Models\Translation;
use App\Models\TranslationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TranslationRequest::class)]
class TranslationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function rules(): array
    {
        return (new TranslationRequest)->rules();
    }

    protected function validDataUsingTranslationKeyId(array $overrides = []): array
    {
        $translationKey = TranslationKey::factory()->create();

        $data = [
            'translation_key_id' => $translationKey->id,
            'locale' => Translation::LOCALES[0], // ex : 'en'
            'text' => 'Texte de traduction valide',
        ];

        return array_merge($data, $overrides);
    }

    protected function validDataUsingKey(array $overrides = []): array
    {
        $data = [
            'key' => 'some.translation.key',
            'locale' => Translation::LOCALES[0],
            'text' => 'Texte de traduction valide',
        ];

        return array_merge($data, $overrides);
    }

    #[Test]
    public function it_passes_with_valid_data_using_translation_key_id(): void
    {
        $data = $this->validDataUsingTranslationKeyId();

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes(), 'La validation devrait réussir avec un translation_key_id existant.');
    }

    #[Test]
    public function it_passes_with_valid_data_using_key(): void
    {
        $data = $this->validDataUsingKey();

        $validator = Validator::make($data, $this->rules());
        $this->assertTrue($validator->passes(), 'La validation devrait réussir avec une key fournie.');
    }

    #[Test]
    public function it_fails_if_neither_translation_key_id_nor_key_are_provided(): void
    {
        $data = [
            'locale' => Translation::LOCALES[0],
            'text' => 'Texte de traduction',
        ];

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si ni translation_key_id ni key ne sont fournis.');
        $errors = $validator->errors()->toArray();
        $this->assertTrue(isset($errors['translation_key_id']) || isset($errors['key']));
    }

    #[Test]
    public function it_fails_if_translation_key_id_does_not_exist(): void
    {
        $data = $this->validDataUsingTranslationKeyId(['translation_key_id' => 9999]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si le translation_key_id n\'existe pas.');
        $this->assertArrayHasKey('translation_key_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_locale_is_missing(): void
    {
        $data = $this->validDataUsingTranslationKeyId(['locale' => null]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si locale est manquant.');
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_locale_is_invalid(): void
    {
        $data = $this->validDataUsingTranslationKeyId(['locale' => 'de']);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si locale n\'est pas autorisé.');
        $this->assertArrayHasKey('locale', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_text_is_missing(): void
    {
        $data = $this->validDataUsingTranslationKeyId(['text' => null]);

        $validator = Validator::make($data, $this->rules());
        $this->assertFalse($validator->passes(), 'La validation devrait échouer si text est manquant.');
        $this->assertArrayHasKey('text', $validator->errors()->toArray());
    }
}
