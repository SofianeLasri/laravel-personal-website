<?php

namespace App\Models;

use Database\Factories\CertificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $level
 * @property string|null $score
 * @property Carbon $date
 * @property string|null $link
 * @property int|null $picture_id
 * @property mixed $use_factory
 * @property int|null $pictures_count
 * @property-read Picture|null $picture
 */
class Certification extends Model
{
    /** @use HasFactory<CertificationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'level',
        'score',
        'date',
        'link',
        'picture_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }
}
