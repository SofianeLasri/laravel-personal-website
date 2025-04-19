<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnologyExperience extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'technology_id',
        'description_translation_key_id',
    ];

    public function technology(): BelongsTo
    {
        return $this->belongsTo(Technology::class);
    }

    public function descriptionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'description_translation_key_id');
    }
}
