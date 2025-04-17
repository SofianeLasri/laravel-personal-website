<?php

namespace App\Http\Requests\Screenshot;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreationDraftScreenshotRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required_with:caption', 'string', 'in:en,fr'],
            'caption' => ['sometimes', 'string'],
        ];
    }
}
