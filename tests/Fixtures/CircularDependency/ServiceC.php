<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests\Fixtures\CircularDependency;

use ScriptMancer\Kiler\Attributes\Service;

#[Service]
class ServiceC
{
    public function __construct(private ?ServiceA $a = null) {}
} 