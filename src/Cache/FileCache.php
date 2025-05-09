<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Cache;

use Scriptmancer\Kiler\Cache\ContainerCacheInterface;
use Scriptmancer\Kiler\Exceptions\NotFoundException;
use Scriptmancer\Kiler\Exceptions\ContainerException;

class FileCache implements ContainerCacheInterface
{
    private string $cacheDir;
    private int $defaultTtl;
    private const METADATA_SUFFIX = '.meta';

    public function __construct(?string $cacheDir = null, int $defaultTtl = 3600)
    {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/kiler-cache';
        $this->defaultTtl = $defaultTtl;

        try {
            if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0777, true)) {
                throw new ContainerException("Failed to create cache directory: {$this->cacheDir}");
            }
            
            if (!is_writable($this->cacheDir)) {
                throw new ContainerException("Cache directory is not writable: {$this->cacheDir}");
            }
        } catch (\Exception $e) {
            throw new ContainerException("Failed to initialize cache directory: {$this->cacheDir}", 0, $e);
        }
    }

    public function has(string $key): bool
    {
        $path = $this->getPath($key);
        if (!file_exists($path)) {
            $this->delete($key); // Clean up any orphaned metadata files
            return false;
        }

        $metadata = $this->readMetadata($key);
        if ($metadata === null) {
            $this->delete($key); // Clean up orphaned data files
            return false;
        }

        if ($metadata['ttl'] > 0 && time() > $metadata['expires_at']) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new NotFoundException("Cache key not found: {$key}");
        }

        $path = $this->getPath($key);
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new ContainerException("Failed to read cache file: {$path}");
        }

        try {
            $unserialized = @unserialize($data);
            if ($unserialized === false) {
                // Delete corrupted data
                $this->delete($key);
                throw new ContainerException("Failed to unserialize cache data for key: {$key}");
            }
            return $unserialized;
        } catch (\Exception $e) {
            // Delete corrupted data
            $this->delete($key);
            throw new ContainerException("Failed to unserialize cache data for key: {$key}", 0, $e);
        }
    }

    public function set(string $key, mixed $data, ?int $ttl = null): void
    {
        $path = $this->getPath($key);
        $ttl = $ttl ?? $this->defaultTtl;

        try {
            $serialized = serialize($data);
        } catch (\Exception $e) {
            throw new ContainerException("Failed to serialize data for key: {$key}", 0, $e);
        }

        // Write data to a temporary file first
        $tempPath = $path . '.tmp';
        if (file_put_contents($tempPath, $serialized, LOCK_EX) === false) {
            throw new ContainerException("Failed to write temporary cache file: {$tempPath}");
        }

        // Write metadata to a temporary file
        $metadata = [
            'created_at' => time(),
            'ttl' => $ttl,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'version' => '1.0'
        ];

        try {
            $serializedMeta = serialize($metadata);
        } catch (\Exception $e) {
            unlink($tempPath);
            throw new ContainerException("Failed to serialize metadata for key: {$key}", 0, $e);
        }

        $metaPath = $this->getMetadataPath($key);
        $tempMetaPath = $metaPath . '.tmp';
        
        $metaDir = dirname($metaPath);
        if (!is_dir($metaDir)) {
            if (!mkdir($metaDir, 0777, true)) {
                unlink($tempPath);
                throw new ContainerException("Failed to create metadata directory: {$metaDir}");
            }
        }

        if (file_put_contents($tempMetaPath, $serializedMeta, LOCK_EX) === false) {
            unlink($tempPath);
            throw new ContainerException("Failed to write temporary metadata file: {$tempMetaPath}");
        }

        // Atomically rename the temporary files to their final names
        if (!rename($tempPath, $path)) {
            unlink($tempPath);
            unlink($tempMetaPath);
            throw new ContainerException("Failed to rename temporary cache file: {$tempPath}");
        }

        if (!rename($tempMetaPath, $metaPath)) {
            unlink($path);
            unlink($tempMetaPath);
            throw new ContainerException("Failed to rename temporary metadata file: {$tempMetaPath}");
        }
    }

    public function delete(string $key): void
    {
        $path = $this->getPath($key);
        $metaPath = $this->getMetadataPath($key);
        $tempPath = $path . '.tmp';
        $tempMetaPath = $metaPath . '.tmp';

        // Delete all possible files
        foreach ([$path, $metaPath, $tempPath, $tempMetaPath] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function clear(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($this->cacheDir, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($file->isDir()) {
                rmdir($path);
            } else {
                // Delete both regular and temporary files
                unlink($path);
                if (file_exists($path . '.tmp')) {
                    unlink($path . '.tmp');
                }
            }
        }
    }

    public function getMetadata(string $key): array
    {
        $metadata = $this->readMetadata($key);
        if ($metadata === null) {
            throw new NotFoundException("Metadata not found for key: {$key}");
        }
        return $metadata;
    }

    private function getPath(string $key): string
    {
        $path = $this->cacheDir;
        $hash = md5($key);
        
        // Create subdirectories based on the first two characters of the hash
        $path .= '/' . substr($hash, 0, 2);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        
        return $path . '/' . substr($hash, 2);
    }

    private function getMetadataPath(string $key): string
    {
        return $this->getPath($key) . self::METADATA_SUFFIX;
    }

    private function readMetadata(string $key): ?array
    {
        $metaPath = $this->getMetadataPath($key);
        if (!file_exists($metaPath)) {
            return null;
        }

        $data = @file_get_contents($metaPath);
        if ($data === false) {
            return null;
        }

        try {
            $unserialized = @unserialize($data);
            if ($unserialized === false) {
                return null;
            }
            return $unserialized;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function setMetadata(string $key, array $metadata): void
    {
        $metaPath = $this->getMetadataPath($key);
        $metaDir = dirname($metaPath);
        
        if (!is_dir($metaDir)) {
            if (!mkdir($metaDir, 0777, true)) {
                throw new ContainerException("Failed to create metadata directory: {$metaDir}");
            }
        }

        try {
            $serialized = serialize($metadata);
        } catch (\Exception $e) {
            throw new ContainerException("Failed to serialize metadata for key: {$key}", 0, $e);
        }

        if (file_put_contents($metaPath, $serialized) === false) {
            throw new ContainerException("Failed to write metadata file: {$metaPath}");
        }
    }
} 