<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExperienceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'in:fr,en'],
            'title' => ['required', 'string'],
            'organization_name' => ['required'],
            'logo_id' => ['nullable', 'exists:pictures,id'],
            'type' => ['required'],
            'location' => ['required'],
            'website_url' => ['nullable'],
            'short_description' => ['required', 'string'],
            'full_description' => ['required', 'string'],
            'technologies' => ['array'],
            'technologies.*' => ['exists:technologies,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
        ];
    }
}
