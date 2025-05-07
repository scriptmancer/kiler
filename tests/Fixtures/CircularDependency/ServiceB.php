<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests\Fixtures\CircularDependency;

use ScriptMancer\Kiler\Attributes\Service;

#[Service]
class ServiceB
{
    public function __construct(private ?ServiceA $a = null) {}
} 