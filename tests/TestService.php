<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Tests;

use ScriptMancer\Kiler\Attributes\Service;

interface TestInterface
{
    public function test(): string;
}

#[Service]
class TestService implements TestInterface
{
    public function __construct()
    {
    }

    public function test(): string
    {
        return 'test';
    }
}

#[Service]
class DependentService
{
    public function __construct(
        private readonly TestService $testService
    ) {}

    public function getDependency(): TestService
    {
        return $this->testService;
    }
} 