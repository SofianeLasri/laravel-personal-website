<?php

namespace App\Models;

use Database\Factories\CustomEmojiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property int $picture_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Picture $picture
 */
class CustomEmoji extends Model
{
    /** @use HasFactory<CustomEmojiFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'picture_id',
    ];

    /**
     * @return BelongsTo<Picture, $this>
     */
    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }

    /**
     * Get optimized pictures for the configured size and formats.
     *
     * @return Collection<int, OptimizedPicture>
     */
    public function getOptimizedPicturesForRendering(): Collection
    {
        $formats = config('emoji.formats', ['webp', 'png']);
        $size = config('emoji.size', 'thumbnail');

        return $this->picture->optimizedPictures()
            ->whereIn('format', $formats)
            ->where('size', $size)
            ->orderByRaw('FIELD(format, '. implode(',', array_map(fn($f) => "'$f'", $formats)) .')')
            ->get();
    }

    /**
     * Get the preview URL for the emoji (first available optimized format).
     */
    public function getPreviewUrl(): ?string
    {
        $optimized = $this->getOptimizedPicturesForRendering()->first();

        if ($optimized) {
            return Storage::url($optimized->path);
        }

        // Fallback to original if no optimized versions available yet
        if ($this->picture->path_original) {
            return Storage::url($this->picture->path_original);
        }

        return null;
    }

    /**
     * Validate emoji name according to configuration rules.
     */
    public static function isValidName(string $name): bool
    {
        $pattern = config('emoji.name_pattern', '/^[a-zA-Z0-9_]+$/');
        $minLength = config('emoji.name_min_length', 2);
        $maxLength = config('emoji.name_max_length', 50);

        if (strlen($name) < $minLength || strlen($name) > $maxLength) {
            return false;
        }

        return preg_match($pattern, $name) === 1;
    }
}
