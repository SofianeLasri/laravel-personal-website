<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $creations_count
 * @property int|null $creation_drafts_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Creation[] $creations
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CreationDraft[] $creationDrafts
 *
 * @method static \Database\Factories\TagFactory<self> factory($count = null, $state = [])
 */
class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
    ];

    public function creations(): BelongsToMany
    {
        return $this->belongsToMany(Creation::class);
    }

    public function creationDrafts(): BelongsToMany
    {
        return $this->belongsToMany(CreationDraft::class, 'creation_draft_tag', 'tag_id', 'creation_draft_id');
    }
}
