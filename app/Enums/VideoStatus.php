<?php

namespace App\Enums;

enum VideoStatus: string
{
    case PENDING = 'pending';
    case TRANSCODING = 'transcoding';
    case READY = 'ready';
    case ERROR = 'error';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
