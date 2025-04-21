<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialMediaLink extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'icon_svg',
        'name',
        'url',
    ];
}
