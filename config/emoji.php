<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Emoji Rendering Formats
    |--------------------------------------------------------------------------
    |
    | The image formats to use when rendering custom emojis in the public site.
    | Order matters: first format is the preferred format, with fallbacks to
    | subsequent formats. Uses <picture> element with <source> tags.
    |
    | Available formats: 'avif', 'webp', 'png', 'jpg'
    | Note: WebP is recommended over AVIF due to Imagick transparency issues.
    |
    */
    'formats' => ['webp', 'jpg'],

    /*
    |--------------------------------------------------------------------------
    | Picture Variant
    |--------------------------------------------------------------------------
    |
    | Which Picture variant to use for emoji rendering.
    | Available variants: 'thumbnail', 'small', 'medium', 'large', 'full'
    |
    | Recommended: 'thumbnail' (150px) for inline emoji usage
    |
    */
    'variant' => 'thumbnail',

    /*
    |--------------------------------------------------------------------------
    | Upload Validation
    |--------------------------------------------------------------------------
    |
    | Maximum file size for emoji uploads (in kilobytes) and allowed formats.
    |
    */
    'max_file_size' => 500, // KB

    'allowed_upload_formats' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],

    /*
    |--------------------------------------------------------------------------
    | Emoji Name Validation
    |--------------------------------------------------------------------------
    |
    | Regex pattern for validating custom emoji names.
    | Default: alphanumeric and underscores only
    |
    */
    'name_pattern' => '/^[a-zA-Z0-9_]+$/',

    'name_min_length' => 2,
    'name_max_length' => 50,
];
