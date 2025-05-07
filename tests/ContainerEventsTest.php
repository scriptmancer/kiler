<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests;

use PHPUnit\Framework\TestCase;
use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\Event\{EventDispatcher, ServiceRegisteredEvent, ServiceResolvedEvent};
use ScriptMancer\Kiler\Tests\{TestService, DependentService};

class ContainerEventsTest extends TestCase
{
    private Container $container;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->container = Container::getInstance();
        $this->container->setEventDispatcher($this->eventDispatcher);
        $this->container->clear();
    }

    public function testServiceRegisteredEvent(): void
    {
        $eventReceived = false;
        $this->eventDispatcher->addListener('container.service.registered', function (array $data) use (&$eventReceived) {
            $eventReceived = true;
            $event = $data['event'];
            
            $this->assertInstanceOf(ServiceRegisteredEvent::class, $event);
            $this->assertEquals(TestService::class, $event->serviceId);
            $this->assertEquals(TestService::class, $event->serviceClass);
            $this->assertTrue($event->singleton);
        });

        $this->container->register(TestService::class);
        $this->assertTrue($eventReceived);
    }

    public function testServiceResolvedEvent(): void
    {
        $this->container->register(TestService::class);

        $eventReceived = false;
        $this->eventDispatcher->addListener('container.service.resolved', function (array $data) use (&$eventReceived) {
            $eventReceived = true;
            $event = $data['event'];
            
            $this->assertInstanceOf(ServiceResolvedEvent::class, $event);
            $this->assertEquals(TestService::class, $event->serviceId);
            $this->assertInstanceOf(TestService::class, $event->instance);
            $this->assertFalse($event->fromCache);
            $this->assertEmpty($event->dependencies);
        });

        $this->container->get(TestService::class);
        $this->assertTrue($eventReceived);
    }

    public function testServiceResolvedFromCacheEvent(): void
    {
        $this->container->register(TestService::class);
        $this->container->get(TestService::class); // First resolution

        $eventReceived = false;
        $this->eventDispatcher->addListener('container.service.resolved', function (array $data) use (&$eventReceived) {
            $eventReceived = true;
            $event = $data['event'];
            
            $this->assertInstanceOf(ServiceResolvedEvent::class, $event);
            $this->assertEquals(TestService::class, $event->serviceId);
            $this->assertInstanceOf(TestService::class, $event->instance);
            $this->assertTrue($event->fromCache);
            $this->assertEmpty($event->dependencies);
        });

        $this->container->get(TestService::class); // Second resolution (from cache)
        $this->assertTrue($eventReceived);
    }

    public function testServiceResolvedWithDependenciesEvent(): void
    {
        $this->container->register(TestService::class);
        $this->container->register(DependentService::class);

        $events = [];
        $this->eventDispatcher->addListener('container.service.resolved', function (array $data) use (&$events) {
            $events[] = $data['event'];
        });

        $this->container->get(DependentService::class);
        
        $this->assertCount(2, $events);
        
        // First event should be for TestService (dependency)
        $this->assertInstanceOf(ServiceResolvedEvent::class, $events[0]);
        $this->assertEquals(TestService::class, $events[0]->serviceId);
        $this->assertInstanceOf(TestService::class, $events[0]->instance);
        $this->assertFalse($events[0]->fromCache);
        $this->assertEmpty($events[0]->dependencies);
        
        // Second event should be for DependentService
        $this->assertInstanceOf(ServiceResolvedEvent::class, $events[1]);
        $this->assertEquals(DependentService::class, $events[1]->serviceId);
        $this->assertInstanceOf(DependentService::class, $events[1]->instance);
        $this->assertFalse($events[1]->fromCache);
        $this->assertNotEmpty($events[1]->dependencies);
    }
} 