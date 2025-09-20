<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Processing Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for image processing, including
    | multiple driver support and fallback mechanisms.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | The default image processing driver to use. This driver will be tried
    | first for all image processing operations.
    |
    | Supported: "imagick", "gd"
    |
    */

    'default' => env('IMAGE_DRIVER', 'imagick'),

    /*
    |--------------------------------------------------------------------------
    | Driver Priority Order
    |--------------------------------------------------------------------------
    |
    | The order in which drivers should be attempted. The first available
    | driver in this list will be used. If it fails and fallback is enabled,
    | the next driver will be tried.
    |
    */

    'drivers' => [
        'imagick',
        'gd',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Enable automatic fallback to alternative drivers when the primary
    | driver fails for specific error types.
    |
    */

    'fallback' => [
        'enabled' => env('IMAGE_FALLBACK_ENABLED', true),

        // Maximum number of drivers to try before giving up
        'max_attempts' => 2,

        // Whether to log fallback attempts
        'log_attempts' => true,

        // Whether to create notifications for fallback usage
        'notify_on_fallback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for specific image processing drivers.
    |
    */

    'imagick' => [
        // Whether to check Imagick resource limits
        'check_resource_limits' => true,

        // Resource limits configuration
        'resource_limits' => [
            'memory' => env('IMAGICK_MEMORY_LIMIT', '256MB'),
            'map' => env('IMAGICK_MAP_LIMIT', '512MB'),
            'width' => env('IMAGICK_WIDTH_LIMIT', 16000),
            'height' => env('IMAGICK_HEIGHT_LIMIT', 16000),
            'area' => env('IMAGICK_AREA_LIMIT', 128000000), // 128 MP
            'disk' => env('IMAGICK_DISK_LIMIT', '1GB'),
            'file' => env('IMAGICK_FILE_LIMIT', 768),
            'thread' => env('IMAGICK_THREAD_LIMIT', 1),
            'throttle' => env('IMAGICK_THROTTLE_LIMIT', 0),
            'time' => env('IMAGICK_TIME_LIMIT', 3600),
        ],

        // Default options for ImageManager
        'options' => [
            'autoOrientation' => true,
            'decodeAnimation' => false,
            'blendingColor' => 'ffffff',
        ],
    ],

    'gd' => [
        // Memory limit for GD operations
        'memory_limit' => env('GD_MEMORY_LIMIT', '128M'),

        // Maximum image dimensions
        'max_width' => env('GD_MAX_WIDTH', 8000),
        'max_height' => env('GD_MAX_HEIGHT', 8000),

        // Default options for ImageManager
        'options' => [
            'autoOrientation' => true,
            'decodeAnimation' => false,
            'blendingColor' => 'ffffff',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Support
    |--------------------------------------------------------------------------
    |
    | Define which formats are supported by each driver. This is used for
    | automatic format fallback when a driver doesn't support a format.
    |
    */

    'format_support' => [
        'imagick' => ['avif', 'webp', 'jpeg', 'png', 'gif', 'bmp', 'tiff'],
        'gd' => ['avif', 'webp', 'jpeg', 'png', 'gif', 'bmp'], // Note: GD supports AVIF since PHP 8.1
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Fallbacks
    |--------------------------------------------------------------------------
    |
    | When a driver doesn't support a specific format, automatically
    | fallback to these alternative formats.
    |
    */

    'format_fallbacks' => [
        'avif' => 'webp', // If AVIF fails, try WebP
        'webp' => 'jpeg', // If WebP fails, try JPEG
        'png' => 'jpeg',  // If PNG fails, try JPEG (with quality loss warning)
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Settings
    |--------------------------------------------------------------------------
    |
    | Default quality settings for different formats when using fallback
    | drivers that might have different optimal settings.
    |
    */

    'quality' => [
        'jpeg' => 85,
        'webp' => 80,
        'avif' => 75,
        'png' => null, // PNG is lossless
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for error handling and notifications.
    |
    */

    'error_handling' => [
        // Whether to create admin notifications for critical errors
        'notify_admin' => true,

        // Minimum severity level for notifications
        'notification_threshold' => 'warning', // info, warning, error, critical

        // Whether to log all processing attempts
        'detailed_logging' => env('IMAGE_DETAILED_LOGGING', true),

        // Maximum number of retry attempts for failed jobs
        'max_job_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize image processing performance.
    |
    */

    'performance' => [
        // Whether to enable processing time tracking
        'track_processing_time' => true,

        // Warning threshold for slow processing (in seconds)
        'slow_processing_threshold' => 30,

        // Whether to automatically resize very large images before processing
        'auto_resize_large_images' => true,

        // Maximum dimensions for auto-resize
        'auto_resize_threshold' => [
            'width' => 4000,
            'height' => 4000,
        ],
    ],

];
