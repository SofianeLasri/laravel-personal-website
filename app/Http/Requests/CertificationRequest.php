<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'level' => ['required'],
            'score' => ['required'],
            'date' => ['required', 'date'],
            'link' => ['required'],
            'picture_id' => ['required', 'exists:pictures,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
