<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests;

use PHPUnit\Framework\TestCase;
use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\Cache\ContainerCacheInterface;
use ScriptMancer\Kiler\Cache\FileCache;
use ScriptMancer\Kiler\Attributes\{Service, Factory, Inject};
use ScriptMancer\Kiler\Exceptions\{ContainerException, NotFoundException};

class ContainerTest extends TestCase
{
    private Container $container;
    private ContainerCacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = new FileCache(sys_get_temp_dir() . '/kiler-test-cache');
        $this->container = Container::getInstance($this->cache);
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    public function testServiceRegistration(): void
    {
        $this->container->register(TestService::class);
        
        $this->assertTrue($this->container->has(TestService::class));
        $this->assertTrue($this->container->has(TestInterface::class));
        
        $service = $this->container->get(TestService::class);
        $this->assertInstanceOf(TestService::class, $service);
        
        $interface = $this->container->get(TestInterface::class);
        $this->assertInstanceOf(TestService::class, $interface);
    }

    public function testServiceRegistrationWithAlias(): void
    {
        $this->container->register(TestService::class, 'test.alias');
        
        $this->assertTrue($this->container->has('test.alias'));
        $service = $this->container->get('test.alias');
        $this->assertInstanceOf(TestService::class, $service);
    }

    public function testServiceRegistrationWithGroup(): void
    {
        $this->container->register(TestService::class);
        
        $this->assertTrue($this->container->hasServiceInGroup('test', TestService::class));
        $this->assertTrue($this->container->hasServiceInGroup('test', TestInterface::class));
        
        $services = $this->container->getServicesByGroup('test');
        $this->assertContains(TestService::class, $services);
        $this->assertContains(TestInterface::class, $services);
    }

    public function testServiceRegistrationWithTags(): void
    {
        $this->container->register(TestService::class);
        
        $this->assertTrue($this->container->hasServiceWithTag('test', TestService::class));
        $this->assertTrue($this->container->hasServiceWithTag('test', TestInterface::class));
        
        $services = $this->container->getServicesByTag('test');
        $this->assertContains(TestService::class, $services);
        $this->assertContains(TestInterface::class, $services);
    }

    public function testServiceRegistrationWithoutServiceAttribute(): void
    {
        $this->expectException(ContainerException::class);
        $this->container->register(InvalidService::class);
    }

    public function testServiceNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('nonexistent.service');
    }

    public function testServiceDependencyInjection(): void
    {
        $this->container->register(TestService::class);
        $this->container->register(DependentService::class);
        
        $service = $this->container->get(DependentService::class);
        $this->assertInstanceOf(DependentService::class, $service);
        $this->assertInstanceOf(TestService::class, $service->getDependency());
    }

    public function testServiceSingleton(): void
    {
        $this->container->register(TestService::class);
        
        $service1 = $this->container->get(TestService::class);
        $service2 = $this->container->get(TestService::class);
        
        $this->assertSame($service1, $service2);
    }

    public function testServiceFactory(): void
    {
        $this->container->registerFactory('test.factory', function() {
            return new TestService();
        });
        
        $service = $this->container->get('test.factory');
        $this->assertInstanceOf(TestService::class, $service);
    }

    public function testCachePersistence(): void
    {
        // Register a service
        $this->container->register(TestService::class);
        
        // Create a new container instance with the same cache
        $newContainer = Container::getInstance($this->cache);
        
        // Verify the service is still available
        $this->assertTrue($newContainer->has(TestService::class));
        $service = $newContainer->get(TestService::class);
        $this->assertInstanceOf(TestService::class, $service);
    }

    public function testCacheVersioning(): void
    {
        // Register a service
        $this->container->register(TestService::class);
        
        // Create a new container instance
        $newContainer = Container::getInstance($this->cache);
        
        // Verify the service is available
        $this->assertTrue($newContainer->has(TestService::class));
        
        // Corrupt the cache data with wrong version
        $this->cache->set(Container::CACHE_KEY, [
            'version' => '2.0',
            'services' => []
        ]);
        
        // Create another container instance with a new cache
        $newCache = new FileCache(sys_get_temp_dir() . '/kiler-test-cache-2');
        $anotherContainer = Container::getInstance($newCache);
        
        // Verify the service is not available (cache invalidated)
        $this->assertFalse($anotherContainer->has(TestService::class));
    }

    public function testCacheExpiration(): void
    {
        // Register a service
        $this->container->register(TestService::class);
        
        // Create a new container instance
        $newContainer = Container::getInstance($this->cache);
        
        // Verify the service is available
        $this->assertTrue($newContainer->has(TestService::class));
        
        // Clear the cache
        $this->cache->delete(Container::CACHE_KEY);
        
        // Create another container instance with a new cache
        $newCache = new FileCache(sys_get_temp_dir() . '/kiler-test-cache-2');
        $anotherContainer = Container::getInstance($newCache);
        
        // Verify the service is not available (cache expired)
        $this->assertFalse($anotherContainer->has(TestService::class));
    }

    public function testCacheClear(): void
    {
        // Register a service
        $this->container->register(TestService::class);
        
        // Create a new container instance
        $newContainer = Container::getInstance($this->cache);
        
        // Verify the service is available
        $this->assertTrue($newContainer->has(TestService::class));
        
        // Clear the cache
        $this->cache->clear();
        
        // Create another container instance with a new cache
        $newCache = new FileCache(sys_get_temp_dir() . '/kiler-test-cache-2');
        $anotherContainer = Container::getInstance($newCache);
        
        // Verify the service is not available (cache cleared)
        $this->assertFalse($anotherContainer->has(TestService::class));
    }
}

// Test interfaces and classes
interface TestInterface
{
    public function test(): string;
}

#[Service(
    implements: TestInterface::class,
    group: 'test',
    tags: ['test'],
    singleton: true
)]
class TestService implements TestInterface
{
    public function test(): string
    {
        return 'test';
    }
}

class InvalidService
{
}

#[Service(singleton: true)]
class DependentService
{
    public function __construct(
        private readonly TestService $dependency
    ) {}

    public function getDependency(): TestService
    {
        return $this->dependency;
    }
} 