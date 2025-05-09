<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Cache;

use Scriptmancer\Kiler\Exceptions\NotFoundException;

class ArrayCache implements ContainerCacheInterface
{
    private array $data = [];
    private array $metadata = [];

    public function has(string $key): bool
    {
        if (!isset($this->data[$key])) {
            return false;
        }

        $metadata = $this->metadata[$key];
        if ($metadata['ttl'] === 0) {
            return true;
        }

        return $metadata['expires_at'] > time();
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new NotFoundException("Cache key not found: $key");
        }

        return $this->data[$key];
    }

    public function set(string $key, mixed $data, ?int $ttl = null): void
    {
        $this->data[$key] = $data;
        $now = time();
        $this->metadata[$key] = [
            'created_at' => $now,
            'ttl' => $ttl ?? 3600,
            'expires_at' => ($ttl === null || $ttl > 0) ? $now + ($ttl ?? 3600) : 0,
            'version' => '1.0'
        ];
    }

    public function delete(string $key): void
    {
        unset($this->data[$key], $this->metadata[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
        $this->metadata = [];
    }

    public function getMetadata(string $key): array
    {
        if (!isset($this->metadata[$key])) {
            throw new NotFoundException("Cache key not found: $key");
        }

        return $this->metadata[$key];
    }
} 