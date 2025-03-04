<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizedPicture extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'picture_id',
        'variant',
        'path',
        'format',
    ];

    const THUMBNAIL_SIZE = 256;

    const SMALL_SIZE = 512;

    const MEDIUM_SIZE = 1024;

    const LARGE_SIZE = 2048;

    const VARIANTS = [
        'thumbnail',
        'small',
        'medium',
        'large',
        'full',
    ];

    const FORMATS = [
        'avif',
        'webp',
    ];

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }
}
