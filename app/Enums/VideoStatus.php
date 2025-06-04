<?php

namespace App\Enums;

enum VideoStatus: string
{
    case PENDING = 'pending';
    case TRANSCODING = 'transcoding';
    case READY = 'ready';
    case ERROR = 'error';
}
