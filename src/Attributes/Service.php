<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Service
{
    public function __construct(
        public readonly ?string $implements = null,
        public readonly ?string $group = null,
        public readonly bool $singleton = true,
        public readonly array $tags = [],
        public readonly ?int $priority = null
    ) {}
} 