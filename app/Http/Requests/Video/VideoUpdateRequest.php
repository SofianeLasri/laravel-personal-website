<?php

namespace App\Http\Requests\Video;

use App\Enums\VideoVisibility;
use Illuminate\Foundation\Http\FormRequest;

class VideoUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'cover_picture_id' => ['nullable', 'exists:pictures,id'],
            'visibility' => ['sometimes', 'in:'.implode(',', VideoVisibility::values())],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
