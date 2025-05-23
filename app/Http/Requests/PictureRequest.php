<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class PictureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'picture' => [
                'required',
                'file',
                'dimensions:max_width='.config('app.imagick.max_width').',max_height='.config('app.imagick.max_height'),
                File::types(config('app.supported_image_formats'))
                    ->max('50mb'),
            ],
        ];
    }
}
