<?php

namespace App\Http\Requests\Video;

use App\Enums\VideoVisibility;
use Illuminate\Foundation\Http\FormRequest;

class VideoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'cover_picture_id' => ['nullable', 'exists:pictures,id'],
            'visibility' => ['required', 'in:'.implode(',', VideoVisibility::values())],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
