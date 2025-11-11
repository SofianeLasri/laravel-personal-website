<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class CustomEmojiRequest extends FormRequest
{
    public function rules(): array
    {
        $allowedFormats = config('emoji.allowed_upload_formats', ['png', 'jpg', 'jpeg', 'webp', 'svg']);
        $maxSize = config('emoji.max_file_size', 500); // KB
        $nameMinLength = config('emoji.name_min_length', 2);
        $nameMaxLength = config('emoji.name_max_length', 50);
        $namePattern = config('emoji.name_pattern', '/^[a-zA-Z0-9_]+$/');

        // Extract the regex pattern without delimiters for Laravel validation
        $pattern = str_replace('/', '\/', trim($namePattern, '/^$'));

        return [
            'name' => [
                'required',
                'string',
                "min:{$nameMinLength}",
                "max:{$nameMaxLength}",
                "regex:{$namePattern}",
                'unique:custom_emojis,name',
            ],
            'picture' => [
                'required',
                'file',
                File::types($allowedFormats)
                    ->max($maxSize.'kb'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de l\'emoji est requis.',
            'name.unique' => 'Un emoji avec ce nom existe déjà.',
            'name.regex' => 'Le nom ne peut contenir que des lettres, chiffres et underscores.',
            'name.min' => 'Le nom doit contenir au moins :min caractères.',
            'name.max' => 'Le nom ne peut pas dépasser :max caractères.',
            'picture.required' => 'L\'image est requise.',
            'picture.file' => 'Le fichier doit être une image valide.',
        ];
    }
}
