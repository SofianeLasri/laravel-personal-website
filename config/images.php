<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the image optimization cache system. This cache is
    | designed to speed up development by avoiding re-optimization of
    | identical images based on their checksum.
    |
    */

    'cache' => [
        /*
        |--------------------------------------------------------------------------
        | Cache Enabled
        |--------------------------------------------------------------------------
        |
        | Whether the image optimization cache is enabled. By default, it's only
        | enabled in local development environment to speed up seeding and testing.
        |
        */
        'enabled' => env('IMAGE_CACHE_ENABLED', env('APP_ENV') === 'local'),

        /*
        |--------------------------------------------------------------------------
        | Cache Driver
        |--------------------------------------------------------------------------
        |
        | The cache driver to use for storing optimized images. Currently
        | supports 'redis' for fast in-memory caching.
        |
        */
        'driver' => env('IMAGE_CACHE_DRIVER', 'redis'),

        /*
        |--------------------------------------------------------------------------
        | Cache TTL (Time To Live)
        |--------------------------------------------------------------------------
        |
        | How long cached optimizations should be kept in seconds.
        | Default: 7 days (7 * 24 * 3600 = 604800 seconds)
        |
        */
        'ttl' => env('IMAGE_CACHE_TTL', 7 * 24 * 3600),

        /*
        |--------------------------------------------------------------------------
        | Hash Algorithm
        |--------------------------------------------------------------------------
        |
        | The hash algorithm to use for generating checksums of source images.
        | Supported: 'md5', 'sha256'
        |
        */
        'hash_algo' => env('IMAGE_CACHE_HASH', 'md5'),

        /*
        |--------------------------------------------------------------------------
        | Compression
        |--------------------------------------------------------------------------
        |
        | Whether to compress cached data to save Redis memory.
        | Uses gzip compression for the cached image data.
        |
        */
        'compress' => env('IMAGE_CACHE_COMPRESS', true),

        /*
        |--------------------------------------------------------------------------
        | Cache Key Prefix
        |--------------------------------------------------------------------------
        |
        | Prefix for all cache keys to avoid conflicts with other cached data.
        |
        */
        'key_prefix' => env('IMAGE_CACHE_PREFIX', 'image_cache'),
    ],
];
