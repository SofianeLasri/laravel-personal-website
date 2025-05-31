<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VideoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'filename' => ['required'],
            'cover_picture_id' => ['required', 'exists:pictures,id'],
            'bunny_video_id' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
