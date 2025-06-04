<?php

namespace App\Models;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
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
        'status',
        'visibility',
    ];

    protected $casts = [
        'status' => VideoStatus::class,
        'visibility' => VideoVisibility::class,
    ];

    public function coverPicture(): BelongsTo
    {
        return $this->belongsTo(Picture::class, 'cover_picture_id');
    }
}
