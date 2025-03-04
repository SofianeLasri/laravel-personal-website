<?php

namespace App\Http\Requests;

use App\Models\Translation;
use Illuminate\Foundation\Http\FormRequest;

class TranslationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'translation_key_id' => ['required_without:key', 'exists:translation_keys,id'],
            'key' => ['required_without:translation_key_id', 'string'],
            'locale' => ['required', 'in:'.implode(',', Translation::LOCALES)],
            'text' => ['required', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
