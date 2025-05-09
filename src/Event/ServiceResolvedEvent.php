<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Event;

class ServiceResolvedEvent
{
    public function __construct(
        public readonly string $serviceId,
        public readonly object $instance,
        public readonly bool $fromCache = false,
        public readonly array $dependencies = []
    ) {}
} 