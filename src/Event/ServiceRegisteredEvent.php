<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Event;

class ServiceRegisteredEvent
{
    public function __construct(
        public readonly string $serviceId,
        public readonly string $serviceClass,
        public readonly ?string $alias = null,
        public readonly ?string $group = null,
        public readonly array $tags = [],
        public readonly bool $singleton = true,
        public readonly ?string $implements = null
    ) {}
} 