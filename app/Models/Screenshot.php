<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screenshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'creation_id',
        'picture_id',
        'caption_translation_key_id',
    ];

    public function creation(): BelongsTo
    {
        return $this->belongsTo(Creation::class);
    }

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    public function captionTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'caption_translation_key_id');
    }

    public function getCaption(string $locale): string
    {
        if ($this->captionTranslationKey()->exists()) {
            return $this->captionTranslationKey->translations()->where('locale', $locale)->value('text');
        }

        return '';
    }
}
