<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certification extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'score',
        'date',
        'link',
        'picture_id',
    ];

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
