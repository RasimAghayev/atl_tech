<?php

declare(strict_types=1);

namespace App\Support\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    private const int DEFAULT_TTL = 3600; // 1 hour

    private const int TAGS_TTL = 7200; // 2 hours

    /**
     * Get data from cache or execute callback and store result
     */
    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache remember failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Fall back to executing callback directly
            return $callback();
        }
    }

    /**
     * Get data from cache with tags
     */
    public function rememberWithTags(array $tags, string $key, callable $callback, int $ttl = self::TAGS_TTL): mixed
    {
        try {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Tagged cache remember failed', [
                'key' => $key,
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return $callback();
        }
    }

    /**
     * Store data in cache
     */
    public function put(string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool
    {
        try {
            return Cache::put($key, $value, $ttl);
        } catch (\Exception $e) {
            Log::warning('Cache put failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store data in cache with tags
     */
    public function putWithTags(array $tags, string $key, mixed $value, int $ttl = self::TAGS_TTL): bool
    {
        try {
            Cache::tags($tags)->put($key, $value, $ttl);

            return true;
        } catch (\Exception $e) {
            Log::warning('Tagged cache put failed', [
                'key' => $key,
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete from cache
     */
    public function forget(string $key): bool
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning('Cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Flush cache by tags
     */
    public function flushTags(array $tags): bool
    {
        try {
            Cache::tags($tags)->flush();

            return true;
        } catch (\Exception $e) {
            Log::warning('Cache flush tags failed', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate cache key for model-based operations
     */
    public function modelKey(string $model, string $operation, array $params = []): string
    {
        $baseKey = strtolower(class_basename($model)).'.'.$operation;

        if (!empty($params)) {
            $paramString = http_build_query($params);
            $baseKey .= '.'.md5($paramString);
        }

        return $baseKey;
    }

    /**
     * Generate tags for model-based caching
     */
    public function modelTags(string $model): array
    {
        return [
            'model.'.strtolower(class_basename($model)),
            'model.all',
        ];
    }

    /**
     * Cache paginated results
     */
    public function cachePagination(string $model, array $filters, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $key = $this->modelKey($model, 'paginate', $filters);
        $tags = $this->modelTags($model);

        return $this->rememberWithTags($tags, $key, $callback, $ttl);
    }

    /**
     * Cache model by ID
     */
    public function cacheModel(string $model, int $id, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $key = $this->modelKey($model, 'show', ['id' => $id]);
        $tags = $this->modelTags($model);

        return $this->rememberWithTags($tags, $key, $callback, $ttl);
    }

    /**
     * Invalidate model cache
     */
    public function invalidateModel(string $model): bool
    {
        $tags = $this->modelTags($model);

        return $this->flushTags($tags);
    }
}
