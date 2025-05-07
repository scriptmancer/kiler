<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Service
{
    /**
     * @param string|null $id The unique identifier/alias for the service. If null, the class name will be used.
     * @param string|null $implements The interface that this service implements
     * @param string|null $group The service group this service belongs to
     * @param bool $singleton Whether this service should be treated as a singleton
     * @param array $tags Additional tags for the service
     * @param int|null $priority The priority of this service (higher numbers = higher priority)
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $implements = null,
        public readonly ?string $group = null,
        public readonly bool $singleton = true,
        public readonly array $tags = [],
        public readonly ?int $priority = null
    ) {}
} 