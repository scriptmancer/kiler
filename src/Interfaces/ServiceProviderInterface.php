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
} 