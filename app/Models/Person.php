<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $picture_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $pictures_count
 * @property int|null $creations_count
 * @property int|null $creation_drafts_count
 * @property-read \App\Models\Picture|null $picture
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Creation[] $creations
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\CreationDraft[] $creationDrafts
 *
 * @method static \Database\Factories\PersonFactory<self> factory($count = null, $state = [])
 */
class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'picture_id',
    ];

    protected $casts = [
        'name' => 'string',
    ];

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    public function creations(): BelongsToMany
    {
        return $this->belongsToMany(Creation::class);
    }

    public function creationDrafts(): BelongsToMany
    {
        return $this->belongsToMany(CreationDraft::class, 'creation_draft_person', 'person_id', 'creation_draft_id');
    }
}
