<?php

namespace App\Models;

use Database\Factories\GameReviewDraftLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_review_draft_id
 * @property string $type
 * @property string $url
 * @property int $label_translation_key_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $game_review_drafts_count
 * @property int|null $label_translation_keys_count
 * @property-read GameReviewDraft $gameReviewDraft
 * @property-read TranslationKey $labelTranslationKey
 */
class GameReviewDraftLink extends Model
{
    /** @use HasFactory<GameReviewDraftLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'game_review_draft_id',
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
     * @return BelongsTo<GameReviewDraft, $this>
     */
    public function gameReviewDraft(): BelongsTo
    {
        return $this->belongsTo(GameReviewDraft::class);
    }

    /**
     * @return BelongsTo<TranslationKey, $this>
     */
    public function labelTranslationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'label_translation_key_id');
    }
}
