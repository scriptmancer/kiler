<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler;

use Scriptmancer\Kiler\Container;

abstract class AbstractServiceProvider
{
    /**
     * Registers services in the container
     */
    abstract public function register(Container $container): void;

    /**
     * Called after all services are registered
     * Use this for initialization that depends on other services
     */
    abstract public function boot(Container $container): void;

    /**
     * Returns the priority of this provider
     * Higher priority providers are registered first
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Returns the services that this provider depends on
     * These services will be initialized before this provider
     * @return array<string> Array of service IDs
     */
    public function getDependencies(): array
    {
        return [];
    }
}
