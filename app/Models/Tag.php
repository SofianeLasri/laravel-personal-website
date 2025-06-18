<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $creations_count
 * @property int|null $creation_drafts_count
 * @property-read Collection|Creation[] $creations
 * @property-read Collection|CreationDraft[] $creationDrafts
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
    ];

    /**
     * @return BelongsToMany<Creation, $this>
     */
    public function creations(): BelongsToMany
    {
        return $this->belongsToMany(Creation::class);
    }

    /**
     * @return BelongsToMany<CreationDraft, $this>
     */
    public function creationDrafts(): BelongsToMany
    {
        return $this->belongsToMany(CreationDraft::class, 'creation_draft_tag', 'tag_id', 'creation_draft_id');
    }
}
