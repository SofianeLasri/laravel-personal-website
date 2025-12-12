<?php

namespace App\Enums;

enum ContentRenderContext: string
{
    case PUBLIC = 'public';
    case PREVIEW = 'preview';
}
