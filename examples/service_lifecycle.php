<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\Container;
use Scriptmancer\Kiler\Event\EventDispatcher;
use Scriptmancer\Kiler\Attributes\{Service, Inject};

// Example service classes
#[Service(id: 'high.priority', priority: 20)]
class HighPriorityService
{
    public function __construct()
    {
        echo "Creating HighPriorityService\n";
    }
}

#[Service(id: 'low.priority', priority: 50)]
class LowPriorityService
{
    public function __construct()
    {
        echo "Creating LowPriorityService\n";
    }
}

#[Service(id: 'singleton.service', singleton: true)]
class SingletonService
{
    private static int $instanceCount = 0;
    private int $instanceId;

    public function __construct()
    {
        $this->instanceId = ++self::$instanceCount;
        echo "Creating SingletonService instance #{$this->instanceId}\n";
    }

    public function getInstanceId(): int
    {
        return $this->instanceId;
    }
}

#[Service(id: 'transient.service', singleton: false)]
class TransientService
{
    private static int $instanceCount = 0;
    private int $instanceId;

    public function __construct()
    {
        $this->instanceId = ++self::$instanceCount;
        echo "Creating TransientService instance #{$this->instanceId}\n";
    }

    public function getInstanceId(): int
    {
        return $this->instanceId;
    }
}

#[Service(id: 'dependent.service')]
class DependentService
{
    public function __construct(
        private HighPriorityService $highPriority,
        private LowPriorityService $lowPriority,
        private SingletonService $singleton,
        private TransientService $transient
    ) {
        echo "Creating DependentService\n";
    }

    public function getServices(): array
    {
        return [
            'highPriority' => $this->highPriority,
            'lowPriority' => $this->lowPriority,
            'singleton' => $this->singleton,
            'transient' => $this->transient
        ];
    }
}

// Create container and event dispatcher
$container = Container::getInstance();
$dispatcher = new EventDispatcher();
$container->setEventDispatcher($dispatcher);

// Add event listener for service resolution
$dispatcher->addListener('container.service.resolved', function(array $data) {
    $event = $data['event'];
    echo "Service resolved: {$event->serviceId}\n";
    if ($event->fromCache) {
        echo "  (from cache)\n";
    }
    if (!empty($event->dependencies)) {
        echo "  Dependencies: " . implode(', ', $event->dependencies) . "\n";
    }
});

// Register services
$container->register(HighPriorityService::class);
$container->register(LowPriorityService::class);
$container->register(SingletonService::class);
$container->register(TransientService::class);
$container->register(DependentService::class);

// Test service resolution
echo "\nTesting service resolution:\n";
$dependent = $container->get('dependent.service');
$services = $dependent->getServices();

// Test singleton behavior
echo "\nTesting singleton behavior:\n";
$singleton1 = $container->get('singleton.service');
$singleton2 = $container->get('singleton.service');
echo "Singleton instances are the same: " . ($singleton1 === $singleton2 ? 'Yes' : 'No') . "\n";
echo "Singleton instance IDs: {$singleton1->getInstanceId()}, {$singleton2->getInstanceId()}\n";

// Test transient behavior
echo "\nTesting transient behavior:\n";
$transient1 = $container->get('transient.service');
$transient2 = $container->get('transient.service');
echo "Transient instances are the same: " . ($transient1 === $transient2 ? 'Yes' : 'No') . "\n";
echo "Transient instance IDs: {$transient1->getInstanceId()}, {$transient2->getInstanceId()}\n";

// Test priority-based resolution
echo "\nTesting priority-based resolution:\n";
$highPriority = $container->get('high.priority');
$lowPriority = $container->get('low.priority');
echo "High priority service resolved first\n"; 