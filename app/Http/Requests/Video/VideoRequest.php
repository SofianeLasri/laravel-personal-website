<?php

namespace App\Http\Requests\Video;

use Illuminate\Foundation\Http\FormRequest;

class VideoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'cover_picture_id' => ['required', 'exists:pictures,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
