<?php

namespace App\Http\Requests\Feature;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreationDraftFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required_with:title,description', 'string', 'in:en,fr'],
            'title' => ['sometimes', 'string'],
            'description' => ['sometimes', 'string'],
            'picture_id' => ['sometimes', 'exists:pictures,id'],
        ];
    }
}
