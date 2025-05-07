<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests\Fixtures\CircularDependency;

use ScriptMancer\Kiler\Attributes\Service;

#[Service]
class ServiceA
{
    public function __construct(private ?ServiceB $b = null) {}
} 