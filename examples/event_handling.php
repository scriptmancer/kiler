<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\Container;
use Scriptmancer\Kiler\Event\{EventDispatcher, ServiceRegisteredEvent, ServiceResolvedEvent};
use Scriptmancer\Kiler\Attributes\Service;

// Example services
#[Service]
class Logger
{
    private array $logs = [];

    public function log(string $message): void
    {
        $this->logs[] = $message;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}

#[Service]
class UserService
{
    public function __construct(
        private readonly Logger $logger
    ) {}

    public function createUser(string $username): void
    {
        $this->logger->log("Creating user: $username");
    }
}

// Create container and event dispatcher
$container = Container::getInstance();
$eventDispatcher = new EventDispatcher();
$container->setEventDispatcher($eventDispatcher);

// Add event listeners
$eventDispatcher->addListener('container.service.registered', function (array $data) {
    /** @var ServiceRegisteredEvent $event */
    $event = $data['event'];
    echo "Service registered: {$event->serviceId}\n";
});

$eventDispatcher->addListener('container.service.resolved', function (array $data) {
    /** @var ServiceResolvedEvent $event */
    $event = $data['event'];
    echo sprintf(
        "Service resolved: %s (from cache: %s, dependencies: %s)\n",
        $event->serviceId,
        $event->fromCache ? 'yes' : 'no',
        implode(', ', $event->dependencies)
    );
});

// Register services
$container->register(Logger::class);
$container->register(UserService::class);

// Use services
$userService = $container->get(UserService::class);
$userService->createUser('john_doe');

// Get logs
$logger = $container->get(Logger::class);
echo "\nLogs:\n";
foreach ($logger->getLogs() as $log) {
    echo "- $log\n";
}

// Demonstrate cache hit
echo "\nResolving UserService again (should be from cache):\n";
$container->get(UserService::class); 