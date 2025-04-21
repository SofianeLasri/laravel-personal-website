<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialMediaLinkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'icon_svg' => ['required'],
            'name' => ['required'],
            'url' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
