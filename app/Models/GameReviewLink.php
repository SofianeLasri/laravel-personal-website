<?php

namespace App\Models;

use Database\Factories\GameReviewLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_review_id
 * @property string $type
 * @property string $url
 * @property int $label_translation_key_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $game_reviews_count
 * @property int|null $label_translation_keys_count
 * @property-read GameReview $gameReview
 * @property-read TranslationKey $labelTranslationKey
 */
class GameReviewLink extends Model
{
    /** @use HasFactory<GameReviewLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'game_review_id',
        'type',
        'url',
        'label_translation_key_id',
        'order',
    ];

    protected $casts = [
        'type' => 'string',
        'url' => 'string',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<GameReview, $this>
     */
    public function gameReview(): BelongsTo
    {
        return $this->belongsTo(GameReview::class);
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function labelTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'label_translation_key_id');
    }
}
