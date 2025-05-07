<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Cache;

interface ContainerCacheInterface
{
    /**
     * Check if a key exists in the cache
     *
     * @param string $key The cache key
     * @return bool True if the key exists and is not expired
     */
    public function has(string $key): bool;

    /**
     * Get a value from the cache
     *
     * @param string $key The cache key
     * @return mixed The cached value
     * @throws \ScriptMancer\Kiler\Exceptions\NotFoundException If the key does not exist
     */
    public function get(string $key): mixed;

    /**
     * Set a value in the cache
     *
     * @param string $key The cache key
     * @param mixed $data The value to cache
     * @param int|null $ttl Time to live in seconds (null = use default, 0 = never expires)
     * @return void
     */
    public function set(string $key, mixed $data, ?int $ttl = null): void;

    /**
     * Delete a value from the cache
     *
     * @param string $key The cache key
     * @return void
     */
    public function delete(string $key): void;

    /**
     * Clear all values from the cache
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Get metadata for a cache entry
     *
     * @param string $key The cache key
     * @return array{created_at: int, expires_at: int|null, version: string} The metadata
     * @throws \ScriptMancer\Kiler\Exceptions\NotFoundException If the key does not exist
     */
    public function getMetadata(string $key): array;
} 