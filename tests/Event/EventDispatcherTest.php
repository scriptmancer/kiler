<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Tests\Event;

use PHPUnit\Framework\TestCase;
use Scriptmancer\Kiler\Event\EventDispatcher;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testDispatchEvent(): void
    {
        $called = false;
        $this->dispatcher->addListener('test.event', function (array $data) use (&$called) {
            $called = true;
            $this->assertEquals(['key' => 'value'], $data);
        });

        $this->dispatcher->dispatch('test.event', ['key' => 'value']);
        $this->assertTrue($called);
    }

    public function testDispatchEventWithMultipleListeners(): void
    {
        $calls = [];
        $this->dispatcher->addListener('test.event', function () use (&$calls) {
            $calls[] = 'first';
        });
        $this->dispatcher->addListener('test.event', function () use (&$calls) {
            $calls[] = 'second';
        });

        $this->dispatcher->dispatch('test.event');
        $this->assertEquals(['first', 'second'], $calls);
    }

    public function testDispatchEventWithPriority(): void
    {
        $calls = [];
        $this->dispatcher->addListener('test.event', function () use (&$calls) {
            $calls[] = 'low';
        }, 0);
        $this->dispatcher->addListener('test.event', function () use (&$calls) {
            $calls[] = 'high';
        }, 10);

        $this->dispatcher->dispatch('test.event');
        $this->assertEquals(['high', 'low'], $calls);
    }

    public function testRemoveListener(): void
    {
        $called = false;
        $listener = function () use (&$called) {
            $called = true;
        };

        $this->dispatcher->addListener('test.event', $listener);
        $this->dispatcher->removeListener('test.event', $listener);
        $this->dispatcher->dispatch('test.event');

        $this->assertFalse($called);
    }

    public function testHasListeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('test.event'));

        $listener = function () {};
        $this->dispatcher->addListener('test.event', $listener);
        $this->assertTrue($this->dispatcher->hasListeners('test.event'));

        $this->dispatcher->removeListener('test.event', $listener);
        $this->assertFalse($this->dispatcher->hasListeners('test.event'));
    }

    public function testGetListeners(): void
    {
        $listener1 = function () {};
        $listener2 = function () {};

        $this->dispatcher->addListener('test.event', $listener1, 0);
        $this->dispatcher->addListener('test.event', $listener2, 10);

        $listeners = $this->dispatcher->getListeners('test.event');
        $this->assertCount(2, $listeners);
        $this->assertSame($listener2, $listeners[0]); // Higher priority first
        $this->assertSame($listener1, $listeners[1]);
    }
} 