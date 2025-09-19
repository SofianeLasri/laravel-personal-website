<?php

namespace App\Services;

use App\Models\Picture;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionClass;

class ImageCacheService
{
    private string $keyPrefix;

    private string $hashAlgorithm;

    private bool $compressionEnabled;

    private int $ttl;

    public function __construct()
    {
        $this->keyPrefix = config('images.cache.key_prefix', 'image_cache');
        $this->hashAlgorithm = config('images.cache.hash_algo', 'md5');
        $this->compressionEnabled = config('images.cache.compress', true);
        $this->ttl = config('images.cache.ttl', 7 * 24 * 3600);
    }

    /**
     * Calculate a checksum for the given image content
     */
    public function calculateChecksum(string $imageContent): string
    {
        return hash($this->hashAlgorithm, $imageContent);
    }

    /**
     * Get cached optimizations for a given checksum
     *
     * @return array|null Array containing cached optimization data or null if not found
     *
     * @throws InvalidArgumentException
     */
    public function getCachedOptimizations(string $checksum): ?array
    {
        if (! $this->isCacheEnabled()) {
            return null;
        }

        $cacheKey = $this->getCacheKey($checksum);
        $cachedData = Cache::store(config('images.cache.driver'))->get($cacheKey);

        if (! $cachedData) {
            return null;
        }

        if ($this->compressionEnabled) {
            $cachedData = gzuncompress($cachedData);
            if ($cachedData === false) {
                Log::warning('Failed to decompress cached image data', ['checksum' => $checksum]);

                return null;
            }
        }

        $data = json_decode($cachedData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to decode cached image data', [
                'checksum' => $checksum,
                'json_error' => json_last_error_msg(),
            ]);

            return null;
        }

        Log::info('Cache hit for image optimization', ['checksum' => $checksum]);

        return $data;
    }

    /**
     * Store optimized images in cache
     *
     * @param  array  $optimizedImages  Format: ['format' => ['variant' => 'image_content']]
     * @param  int  $width  Source image width
     * @param  int  $height  Source image height
     */
    public function storeCachedOptimizations(string $checksum, array $optimizedImages, int $width, int $height): void
    {
        if (! $this->isCacheEnabled()) {
            return;
        }

        $cacheData = [
            'width' => $width,
            'height' => $height,
            'cached_at' => now()->toISOString(),
            'optimized_files' => $optimizedImages,
        ];

        $jsonData = json_encode($cacheData);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to encode image data for cache', [
                'checksum' => $checksum,
                'json_error' => json_last_error_msg(),
            ]);

            return;
        }

        if ($this->compressionEnabled) {
            $compressedData = gzcompress($jsonData);
            if ($compressedData === false) {
                Log::warning('Failed to compress image data for cache', ['checksum' => $checksum]);

                return;
            }
            $dataToStore = $compressedData;
        } else {
            $dataToStore = $jsonData;
        }

        $cacheKey = $this->getCacheKey($checksum);
        Cache::store(config('images.cache.driver'))->put($cacheKey, $dataToStore, $this->ttl);

        Log::info('Stored image optimization in cache', [
            'checksum' => $checksum,
            'size_bytes' => strlen($dataToStore),
            'compressed' => $this->compressionEnabled,
        ]);
    }

    /**
     * Copy cached files to the target picture's storage locations
     */
    public function copyCachedFiles(array $cachedData, Picture $targetPicture): bool
    {
        if (! $targetPicture->hasValidOriginalPath()) {
            Log::warning('Cannot copy cached files: target picture has no valid original path');

            return false;
        }

        $optimizedFiles = $cachedData['optimized_files'] ?? [];
        $dimensions = [
            'width' => $cachedData['width'],
            'height' => $cachedData['height'],
        ];

        try {
            foreach ($optimizedFiles as $format => $variants) {
                foreach ($variants as $variantName => $imageContent) {
                    // Decode base64 content if needed
                    if (base64_encode(base64_decode($imageContent, true)) === $imageContent) {
                        $imageContent = base64_decode($imageContent);
                    }

                    // Generate the path for this variant
                    $path = Str::beforeLast($targetPicture->path_original, '.')."_{$variantName}.{$format}";

                    // Store the file
                    Storage::disk('public')->put($path, $imageContent);

                    // Also store on CDN if configured
                    if (config('app.cdn_disk')) {
                        Storage::disk(config('app.cdn_disk'))->put($path, $imageContent);
                    }

                    // Create the OptimizedPicture record
                    $targetPicture->optimizedPictures()->create([
                        'variant' => $variantName,
                        'path' => $path,
                        'format' => $format,
                    ]);
                }
            }

            // Update picture dimensions
            $targetPicture->update($dimensions);

            Log::info('Successfully copied cached files for picture', [
                'picture_id' => $targetPicture->id,
                'formats_count' => count($optimizedFiles),
                'variants_count' => array_sum(array_map('count', $optimizedFiles)),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to copy cached files', [
                'picture_id' => $targetPicture->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear all cached optimizations
     */
    public function clearCache(): int
    {
        if (! $this->isCacheEnabled()) {
            return 0;
        }

        $store = Cache::store(config('images.cache.driver'));
        $deletedCount = 0;

        // Handle different cache drivers
        if (method_exists($store, 'getRedis')) {
            // Redis-based cache
            $pattern = $this->getCacheKey('*');
            $redis = $store->getRedis();
            $keys = $redis->keys($pattern);

            if (! empty($keys)) {
                $deletedCount = $redis->del($keys);
            }
        } else {
            // Array or other cache drivers - clear manually
            $pattern = $this->keyPrefix.':';

            // For testing purposes with array cache, we'll use reflection to access the storage
            if (get_class($store->getStore()) === 'Illuminate\Cache\ArrayStore') {
                $reflection = new ReflectionClass($store->getStore());
                $storage = $reflection->getProperty('storage');
                $storage->setAccessible(true);
                $storageArray = $storage->getValue($store->getStore());

                foreach ($storageArray as $key => $value) {
                    if (str_starts_with($key, $pattern)) {
                        $store->forget($key);
                        $deletedCount++;
                    }
                }
            }
        }

        Log::info('Cleared image cache', ['deleted_keys' => $deletedCount]);

        return $deletedCount;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        if (! $this->isCacheEnabled()) {
            return [
                'enabled' => false,
                'total_keys' => 0,
                'total_memory' => 0,
            ];
        }

        $store = Cache::store(config('images.cache.driver'));
        $totalKeys = 0;
        $totalMemory = 0;

        // Handle different cache drivers
        if (method_exists($store, 'getRedis')) {
            // Redis-based cache
            $pattern = $this->getCacheKey('*');
            $redis = $store->getRedis();
            $keys = $redis->keys($pattern);
            $totalKeys = count($keys);

            foreach ($keys as $key) {
                $totalMemory += $redis->memory('usage', $key) ?? 0;
            }
        } else {
            // Array or other cache drivers
            $pattern = $this->keyPrefix.':';

            if (get_class($store->getStore()) === 'Illuminate\Cache\ArrayStore') {
                $reflection = new ReflectionClass($store->getStore());
                $storage = $reflection->getProperty('storage');
                $storage->setAccessible(true);
                $storageArray = $storage->getValue($store->getStore());

                foreach ($storageArray as $key => $value) {
                    if (str_starts_with($key, $pattern)) {
                        $totalKeys++;
                        $totalMemory += is_string($value) ? strlen($value) : 0;
                    }
                }
            }
        }

        return [
            'enabled' => true,
            'total_keys' => $totalKeys,
            'total_memory' => $totalMemory,
            'total_memory_human' => $this->formatBytes($totalMemory),
            'ttl' => $this->ttl,
            'compression_enabled' => $this->compressionEnabled,
            'hash_algorithm' => $this->hashAlgorithm,
        ];
    }

    /**
     * Check if cache is enabled
     */
    private function isCacheEnabled(): bool
    {
        return config('images.cache.enabled', false);
    }

    /**
     * Generate cache key for a checksum
     */
    private function getCacheKey(string $checksum): string
    {
        return "{$this->keyPrefix}:{$checksum}";
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}
