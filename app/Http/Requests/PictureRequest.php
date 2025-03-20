<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class PictureRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'picture' => File::image()
                ->max('50mb')
                ->dimensions(Rule::dimensions()->maxWidth(config('app.imagick.max_width'))->maxHeight(config('app.imagick.max_height'))),
        ];
    }
}
