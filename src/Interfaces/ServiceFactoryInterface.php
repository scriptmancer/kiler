<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Interfaces;

use Scriptmancer\Kiler\Container;

interface ServiceFactoryInterface
{
    /**
     * Creates a service instance
     */
    public function createService(Container $container, string $id, array $arguments = []): object;

    /**
     * Checks if this factory can create the specified service
     */
    public function supports(string $id): bool;
} 