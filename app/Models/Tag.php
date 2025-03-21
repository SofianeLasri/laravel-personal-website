<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
