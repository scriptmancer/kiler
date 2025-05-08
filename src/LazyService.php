<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler;

use ScriptMancer\Kiler\Interfaces\LazyServiceInterface;

class LazyService implements LazyServiceInterface
{
    private ?object $instance = null;

    public function __construct(
        private readonly Container $container,
        private readonly string $serviceId,
        private readonly ?string $group = null,
        private readonly ?string $tag = null
    ) {}

    public function get(): object
    {
        if ($this->instance === null) {
            $this->instance = $this->container->get(
                $this->serviceId,
                $this->group,
                $this->tag
            );
        }

        return $this->instance;
    }
} 