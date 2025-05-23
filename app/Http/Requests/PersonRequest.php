<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'url' => ['nullable', 'string'],
            'picture_id' => ['nullable', 'exists:pictures,id'],
        ];
    }
}
