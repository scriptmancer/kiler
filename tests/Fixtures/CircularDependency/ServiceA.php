<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Tests\Fixtures\CircularDependency;

use Scriptmancer\Kiler\Attributes\Service;

#[Service]
class ServiceA
{
    public function __construct(private ?ServiceB $b = null) {}
} 