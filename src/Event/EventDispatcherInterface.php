<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Event;

interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners
     *
     * @param string $eventName The name of the event to dispatch
     * @param array $data Optional data to pass to the event listeners
     * @return void
     */
    public function dispatch(string $eventName, array $data = []): void;

    /**
     * Add an event listener
     *
     * @param string $eventName The name of the event to listen for
     * @param callable $listener The listener to call when the event is dispatched
     * @param int $priority The priority of the listener (higher numbers are called first)
     * @return void
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void;

    /**
     * Remove an event listener
     *
     * @param string $eventName The name of the event
     * @param callable $listener The listener to remove
     * @return void
     */
    public function removeListener(string $eventName, callable $listener): void;

    /**
     * Check if an event has any listeners
     *
     * @param string $eventName The name of the event
     * @return bool
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Get all listeners for an event
     *
     * @param string $eventName The name of the event
     * @return array<callable>
     */
    public function getListeners(string $eventName): array;
} 