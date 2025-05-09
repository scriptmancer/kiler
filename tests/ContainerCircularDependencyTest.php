<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Tests;

use PHPUnit\Framework\TestCase;
use Scriptmancer\Kiler\Container;
use Scriptmancer\Kiler\Exceptions\ContainerException;
use Scriptmancer\Kiler\Tests\Fixtures\CircularDependency\{ServiceA, ServiceB, ServiceC};

class ContainerCircularDependencyTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
        $this->container->clear();
    }

    public function testDetectsDirectCircularDependency(): void
    {
        $this->container->register(ServiceA::class);
        $this->container->register(ServiceB::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: ' . ServiceA::class . ' -> ' . ServiceB::class . ' -> ' . ServiceA::class);
        
        $this->container->get(ServiceA::class);
    }

    public function testDetectsIndirectCircularDependency(): void
    {
        $this->container->register(ServiceA::class);
        $this->container->register(ServiceB::class);
        $this->container->register(ServiceC::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected: ' . ServiceA::class . ' -> ' . ServiceB::class . ' -> ' . ServiceA::class);
        
        $this->container->get(ServiceA::class);
    }

    public function testNoCircularDependencyWithFactory(): void
    {
        $this->container->register(ServiceA::class);
        $this->container->registerFactory(ServiceB::class, function() {
            return new ServiceB(new ServiceA(null));
        });

        // This should not throw an exception
        $instance = $this->container->get(ServiceA::class);
        $this->assertInstanceOf(ServiceA::class, $instance);
    }
} 