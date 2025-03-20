<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreationDraftScreenshotRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'creation_draft_id' => ['required', 'exists:creation_drafts'],
            'picture_id' => ['required', 'exists:pictures'],
            'caption' => ['sometimes', 'string'],
        ];
    }
}
