<?php

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $picture_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $pictures_count
 * @property int|null $creations_count
 * @property int|null $creation_drafts_count
 * @property-read Picture|null $picture
 * @property-read Collection|Creation[] $creations
 * @property-read Collection|CreationDraft[] $creationDrafts
 *
 * @method static PersonFactory<self> factory($count = null, $state = [])
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
