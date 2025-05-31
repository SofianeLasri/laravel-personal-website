<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'path',
        'cover_picture_id',
        'bunny_video_id',
    ];

    public function coverPicture(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'cover_picture_id');
    }
}
