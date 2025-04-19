<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TechnologyExperienceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'technology_id' => ['required', 'exists:technologies,id'],
            'locale' => ['required', 'string', 'in:fr,en'],
            'description' => ['required', 'string'],
        ];
    }
}
