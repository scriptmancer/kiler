<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Interfaces;

use ScriptMancer\Kiler\Container;

interface ServiceProviderInterface
{
    /**
     * Registers services in the container
     */
    public function register(Container $container): void;

    /**
     * Returns the priority of this provider
     * Higher priority providers are registered first
     */
    public function getPriority(): int;

    /**
     * Returns the services that this provider depends on
     * These services will be initialized before this provider
     * @return array<string> Array of service IDs
     */
    public function getDependencies(): array;

    /**
     * Called after all services are registered
     * Use this for initialization that depends on other services
     */
    public function boot(Container $container): void;
} 