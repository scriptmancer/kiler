<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Inject
{
    public function __construct(
        public readonly ?string $service = null
    ) {}
} 