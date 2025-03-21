<?php

namespace App\Http\Requests\Feature;

use Illuminate\Foundation\Http\FormRequest;

class CreateCreationDraftFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'in:en,fr'],
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'picture_id' => ['sometimes', 'exists:pictures,id'],
        ];
    }
}
