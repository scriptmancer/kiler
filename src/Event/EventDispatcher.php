<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Event;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<array{listener: callable, priority: int}>>
     */
    private array $listeners = [];

    public function dispatch(string $eventName, array $data = []): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        // Sort listeners by priority (highest first)
        $listeners = $this->listeners[$eventName];
        usort($listeners, fn($a, $b) => $b['priority'] <=> $a['priority']);

        foreach ($listeners as ['listener' => $listener]) {
            $listener($data);
        }
    }

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = [
            'listener' => $listener,
            'priority' => $priority
        ];
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $this->listeners[$eventName] = array_filter(
            $this->listeners[$eventName],
            fn($item) => $item['listener'] !== $listener
        );

        if (empty($this->listeners[$eventName])) {
            unset($this->listeners[$eventName]);
        }
    }

    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) && !empty($this->listeners[$eventName]);
    }

    public function getListeners(string $eventName): array
    {
        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        // Sort listeners by priority (highest first)
        $listeners = $this->listeners[$eventName];
        usort($listeners, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return array_map(fn($item) => $item['listener'], $listeners);
    }
} 