<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests\Cache;

use PHPUnit\Framework\TestCase;
use ScriptMancer\Kiler\Cache\ContainerCacheInterface;
use ScriptMancer\Kiler\Cache\ArrayCache;
use ScriptMancer\Kiler\Exceptions\NotFoundException;

class ContainerCacheInterfaceTest extends TestCase
{
    protected ContainerCacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createCache();
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    protected function createCache(): ContainerCacheInterface
    {
        return new ArrayCache();
    }

    public function testHasReturnsFalseForNonExistentKey(): void
    {
        $this->assertFalse($this->cache->has('non_existent_key'));
    }

    public function testGetThrowsExceptionForNonExistentKey(): void
    {
        $this->expectException(NotFoundException::class);
        $this->cache->get('non_existent_key');
    }

    public function testSetAndGetWorkWithSimpleData(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function testSetAndGetWorkWithArrayData(): void
    {
        $key = 'test_array';
        $value = ['key1' => 'value1', 'key2' => 'value2'];
        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function testDeleteRemovesKey(): void
    {
        $key = 'test_delete';
        $this->cache->set($key, 'value');
        $this->assertTrue($this->cache->has($key));
        $this->cache->delete($key);
        $this->assertFalse($this->cache->has($key));
    }

    public function testClearRemovesAllKeys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->clear();
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testGetMetadataReturnsArray(): void
    {
        $key = 'test_metadata';
        $this->cache->set($key, 'value');
        $metadata = $this->cache->getMetadata($key);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('created_at', $metadata);
        $this->assertArrayHasKey('ttl', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);
        $this->assertArrayHasKey('version', $metadata);
    }

    public function testGetMetadataThrowsExceptionForNonExistentKey(): void
    {
        $this->expectException(NotFoundException::class);
        $this->cache->getMetadata('non_existent_key');
    }

    public function testSetWithTTLExpires(): void
    {
        $key = 'test_ttl';
        $this->cache->set($key, 'value', 1);
        $this->assertTrue($this->cache->has($key));
        sleep(2);
        $this->assertFalse($this->cache->has($key));
    }

    public function testSetWithZeroTTLNeverExpires(): void
    {
        $key = 'test_zero_ttl';
        $this->cache->set($key, 'value', 0);
        $this->assertTrue($this->cache->has($key));
        sleep(1);
        $this->assertTrue($this->cache->has($key));
    }
} 