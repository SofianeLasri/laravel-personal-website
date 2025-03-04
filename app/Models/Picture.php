<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Picture extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'width',
        'height',
        'size',
    ];

    /**
     * Get the optimized pictures for the picture.
     *
     * @param  string  $variant  thumbnail|small|medium|large|full
     * @param  string  $format  avif|webp
     */
    public function getOptimizedPicture(string $variant, string $format): ?OptimizedPicture
    {
        return $this->optimizedPictures->first(function (OptimizedPicture $optimizedPicture) use ($variant, $format) {
            return $optimizedPicture->variant === $variant && $optimizedPicture->format === $format;
        });
    }
}
