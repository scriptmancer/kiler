<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\Attributes\Service;

#[Service]
class MyService
{
    public function hello(): string
    {
        return "Hello from MyService!";
    }
}

// GLOBAL SCOPE
// Register service globally
container()->register(MyService::class);

function unrelatedFunctionScope()
{
    // LOCAL SCOPE (but still uses global singleton)
    $service = container()->get(MyService::class);
    echo "[unrelatedFunctionScope] " . $service->hello() . "\n";
}

class UnrelatedClass
{
    public function doSomething()
    {
        // LOCAL SCOPE (method in unrelated class)
        $service = container()->get(MyService::class);
        echo "[UnrelatedClass::doSomething] " . $service->hello() . "\n";
    }
}

// MAIN SCRIPT (GLOBAL SCOPE)
$service = container()->get(MyService::class);
echo "[main script] " . $service->hello() . "\n";

// Call from function scope
unrelatedFunctionScope();

// Call from class method
$obj = new UnrelatedClass();
$obj->doSomething();

// Demonstrate singleton
if (container() === container()) {
    echo "[singleton check] container() returns the same instance everywhere!\n";
}
