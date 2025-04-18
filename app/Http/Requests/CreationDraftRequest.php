<?php

namespace App\Http\Requests;

use App\Enums\CreationType;
use Illuminate\Foundation\Http\FormRequest;

class CreationDraftRequest extends FormRequest
{
    public function rules(): array
    {
        $creationsTypes = CreationType::values();

        return [
            'locale' => ['required', 'string', 'in:fr,en'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'logo_id' => ['sometimes', 'integer', 'exists:pictures,id'],
            'cover_image_id' => ['sometimes', 'integer', 'exists:pictures,id'],
            'type' => ['required', 'string', 'in:'.implode(',', $creationsTypes)],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
            'short_description_content' => ['required', 'string'],
            'full_description_content' => ['required', 'string'],
            'featured' => ['sometimes', 'boolean'],
            'external_url' => ['nullable', 'string', 'url'],
            'source_code_url' => ['nullable', 'string', 'url'],
            'original_creation_id' => ['nullable', 'integer', 'exists:creations,id'],
            'people' => 'nullable|array',
            'people.*' => 'exists:people,id',
            'technologies' => 'nullable|array',
            'technologies.*' => 'exists:technologies,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ];
    }
}
