<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests\Cache;

use ScriptMancer\Kiler\Cache\ContainerCacheInterface;
use ScriptMancer\Kiler\Cache\FileCache;

class FileCacheInterfaceTest extends ContainerCacheInterfaceTest
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/kiler_test_cache';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    protected function createCache(): ContainerCacheInterface
    {
        return new FileCache(sys_get_temp_dir() . '/kiler-test-cache');
    }
} 