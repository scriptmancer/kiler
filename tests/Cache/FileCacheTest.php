<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests\Cache;

use PHPUnit\Framework\TestCase;
use ScriptMancer\Kiler\Cache\FileCache;
use ScriptMancer\Kiler\Exceptions\ContainerException;

class FileCacheTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/kiler_test_cache';
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testCacheDirectoryCreation(): void
    {
        $this->assertDirectoryExists($this->cacheDir);
    }

    public function testCacheSetAndGet(): void
    {
        $key = 'test.key';
        $value = ['test' => 'value'];

        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));
        
        $retrieved = $this->cache->get($key);
        $this->assertEquals($value, $retrieved);
    }

    public function testCacheDelete(): void
    {
        $key = 'test.key';
        $value = ['test' => 'value'];

        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));

        $this->cache->delete($key);
        $this->assertFalse($this->cache->has($key));
    }

    public function testCacheClear(): void
    {
        $keys = ['key1', 'key2', 'key3'];
        $value = ['test' => 'value'];

        foreach ($keys as $key) {
            $this->cache->set($key, $value);
            $this->assertTrue($this->cache->has($key));
        }

        $this->cache->clear();

        foreach ($keys as $key) {
            $this->assertFalse($this->cache->has($key));
        }
    }

    public function testCacheExpiration(): void
    {
        $key = 'test.key';
        $value = ['test' => 'value'];

        $this->cache->set($key, $value, 1);
        $this->assertTrue($this->cache->has($key));

        sleep(2);
        $this->assertFalse($this->cache->has($key));
    }

    public function testCacheMetadata(): void
    {
        $key = 'test.key';
        $value = ['test' => 'value'];

        $this->cache->set($key, $value);
        $metadata = $this->cache->getMetadata($key);

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('created_at', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);
        $this->assertArrayHasKey('version', $metadata);
    }

    public function testCacheWithComplexData(): void
    {
        $key = 'test.key';
        $value = [
            'string' => 'test',
            'int' => 123,
            'float' => 123.45,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => new \stdClass()
        ];

        $this->cache->set($key, $value);
        $retrieved = $this->cache->get($key);

        $this->assertEquals($value['string'], $retrieved['string']);
        $this->assertEquals($value['int'], $retrieved['int']);
        $this->assertEquals($value['float'], $retrieved['float']);
        $this->assertEquals($value['bool'], $retrieved['bool']);
        $this->assertEquals($value['null'], $retrieved['null']);
        $this->assertEquals($value['array'], $retrieved['array']);
        $this->assertEquals($value['object'], $retrieved['object']);
    }

    public function testCacheWithNonSerializableData(): void
    {
        $key = 'test.key';
        $value = function() { return 'test'; };

        $this->expectException(ContainerException::class);
        $this->cache->set($key, $value);
    }

    public function testCacheWithInvalidDirectory(): void
    {
        // Try to create cache in a non-existent root directory
        $invalidDir = '/nonexistent/directory/' . uniqid();
        
        $this->expectException(ContainerException::class);
        new FileCache($invalidDir);
    }

    public function testCacheWithCorruptedData(): void
    {
        $key = 'test.key';
        $hash = md5($key);
        $subDir = $this->cacheDir . '/' . substr($hash, 0, 2);
        if (!is_dir($subDir)) {
            mkdir($subDir, 0777, true);
        }
        $file = $subDir . '/' . substr($hash, 2);
        
        // Write invalid data
        file_put_contents($file, 'invalid data');

        // Write valid metadata
        $metaPath = $file . '.meta';
        $metadata = [
            'created_at' => time(),
            'ttl' => 3600,
            'expires_at' => time() + 3600,
            'version' => '1.0'
        ];
        file_put_contents($metaPath, serialize($metadata));

        // First check if the key exists
        $this->assertTrue($this->cache->has($key));
        
        // Then try to get the corrupted data
        $this->expectException(ContainerException::class);
        $this->cache->get($key);
    }

    public function testClearWithSubdirectories(): void
    {
        // Create a subdirectory in the cache directory
        $subDir = $this->cacheDir . '/subdir';
        mkdir($subDir);

        // Create files in both main directory and subdirectory
        $this->cache->set('main.key', 'main value');
        $this->cache->set('subdir/key', 'subdir value');

        $this->assertTrue($this->cache->has('main.key'));
        $this->assertTrue($this->cache->has('subdir/key'));
        $this->assertTrue(is_dir($subDir));

        // Clear the cache
        $this->cache->clear();

        // Verify everything is cleared
        $this->assertFalse($this->cache->has('main.key'));
        $this->assertFalse($this->cache->has('subdir/key'));
        $this->assertFalse(is_dir($subDir));
    }

    public function testCacheSubdirectoryCreation(): void
    {
        $key = 'test.key';
        $hash = md5($key);
        $subDir = $this->cacheDir . '/' . substr($hash, 0, 2);
        
        $this->cache->set($key, 'value');
        
        $this->assertDirectoryExists($subDir);
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals('value', $this->cache->get($key));
    }
} 