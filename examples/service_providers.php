<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\Event\EventDispatcher;
use ScriptMancer\Kiler\Interfaces\ServiceFactoryInterface;
use ScriptMancer\Kiler\Interfaces\ServiceProviderInterface;
use ScriptMancer\Kiler\Attributes\Service;

// Example service classes
#[Service]
class DatabaseConnection
{
    private string $dsn;
    private array $options;

    public function __construct(string $dsn, array $options = [])
    {
        $this->dsn = $dsn;
        $this->options = $options;
    }

    public function connect(): void
    {
        echo "Connecting to database: {$this->dsn}\n";
    }
}

#[Service]
class UserRepository
{
    private DatabaseConnection $db;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    public function findUser(int $id): array
    {
        $this->db->connect();
        return ['id' => $id, 'name' => 'John Doe'];
    }
}

// Example service factory
class DatabaseConnectionFactory implements ServiceFactoryInterface
{
    public function createService(Container $container, string $id, array $arguments = []): object
    {
        return new DatabaseConnection(
            $arguments['dsn'] ?? 'mysql://localhost:3306/mydb',
            $arguments['options'] ?? []
        );
    }

    public function supports(string $id): bool
    {
        return $id === DatabaseConnection::class;
    }
}

// Example service provider
class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Register database connection
        $container->register(DatabaseConnection::class, null, [
            'arguments' => [
                'dsn' => 'mysql://localhost:3306/mydb',
                'options' => [
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci'
                ]
            ]
        ]);

        // Register user repository
        $container->register(UserRepository::class);
    }

    public function getPriority(): int
    {
        return 100; // High priority since other services might depend on database
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

// Add service factory
$container->addServiceFactory(new DatabaseConnectionFactory());

// Add service provider
$container->addServiceProvider(new DatabaseServiceProvider());

// Register all providers
$container->registerProviders();

// Use the services
$userRepo = $container->get(UserRepository::class);
$user = $userRepo->findUser(1);
echo "Found user: " . json_encode($user) . "\n";

// Try to get the same service again (should use cached instance)
$userRepo2 = $container->get(UserRepository::class);
$user2 = $userRepo2->findUser(2);
echo "Found user: " . json_encode($user2) . "\n"; 