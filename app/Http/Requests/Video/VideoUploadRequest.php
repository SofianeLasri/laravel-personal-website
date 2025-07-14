<?php

namespace App\Http\Requests\Video;

use Illuminate\Foundation\Http\FormRequest;

class VideoUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'video' => [
                'required',
                'file',
                'mimes:mp4,avi,mov,wmv,flv,webm,mkv',
                'max:'.(1024 * 1024 * 2000), // 1000MB max
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'cover_picture_id' => ['nullable', 'sometimes', 'exists:pictures,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'video.required' => 'Le fichier vidéo est requis.',
            'video.file' => 'Le fichier doit être une vidéo valide.',
            'video.mimes' => 'La vidéo doit être au format: mp4, avi, mov, wmv, flv, webm, mkv.',
            'video.max' => 'La vidéo ne doit pas dépasser 2000MB.',
            'cover_picture_id.required' => 'Une image de couverture est requise.',
            'cover_picture_id.exists' => 'L\'image de couverture sélectionnée n\'existe pas.',
        ];
    }
}
