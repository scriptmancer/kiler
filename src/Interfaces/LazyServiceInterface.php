<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Interfaces;

interface LazyServiceInterface
{
    /**
     * Get the actual service instance.
     * The service will be instantiated only when this method is called.
     */
    public function get(): object;
} 