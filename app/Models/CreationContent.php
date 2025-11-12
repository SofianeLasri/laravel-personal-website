<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $creation_id
 * @property string $content_type
 * @property int $content_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $creations_count
 * @property-read Creation|null $creation
 * @property-read Model|null $content
 */
class CreationContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'creation_id',
        'content_type',
        'content_id',
        'order',
    ];

    protected $casts = [
        'content_type' => 'string',
        'content_id' => 'integer',
        'order' => 'integer',
    ];

    /**
     * @return BelongsTo<Creation, $this>
     */
    public function creation(): BelongsTo
    {
        return $this->belongsTo(Creation::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }
}
