<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreationDraftFeatureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'picture_id' => ['sometimes', 'exists:pictures'],
        ];
    }
}
