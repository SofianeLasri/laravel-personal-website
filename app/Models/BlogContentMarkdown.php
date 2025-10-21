<?php

namespace App\Models;

use Database\Factories\BlogContentMarkdownFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $translation_key_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $translation_keys_count
 * @property-read TranslationKey|null $translationKey
 */
class BlogContentMarkdown extends Model
{
    /** @use HasFactory<BlogContentMarkdownFactory> */
    use HasFactory;

    protected $table = 'blog_content_markdown';

    protected $fillable = [
        'translation_key_id',
    ];

    protected $casts = [
        'translation_key_id' => 'integer',
    ];

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function translationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class);
    }
}
