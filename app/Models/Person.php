<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
