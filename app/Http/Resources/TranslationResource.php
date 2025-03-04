<?php

namespace App\Http\Resources;

use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Translation */
class TranslationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'locale' => $this->locale,
            'text' => $this->text,
        ];
    }
}
