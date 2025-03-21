<?php

namespace App\Http\Requests\Screenshot;

use Illuminate\Foundation\Http\FormRequest;

class CreateCreationDraftScreenshotRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required_with:caption', 'string', 'in:en,fr'],
            'picture_id' => ['required', 'exists:pictures,id'],
            'caption' => ['sometimes', 'string'],
        ];
    }
}
