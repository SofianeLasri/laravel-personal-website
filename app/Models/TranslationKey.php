<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TranslationKey extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key',
    ];

    protected $casts = [
        'key' => 'string',
    ];

    /**
     * Find a translation key by its key.
     */
    public static function findByKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }
}
