<?php

namespace App\Models;

use Database\Factories\SocialMediaLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $icon_svg
 * @property string $name
 * @property string $url
 * @property mixed $use_factory
 */
class SocialMediaLink extends Model
{
    /** @use HasFactory<SocialMediaLinkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'icon_svg',
        'name',
        'url',
    ];
}
