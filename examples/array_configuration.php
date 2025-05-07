<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\Event\EventDispatcher;

// Example service classes
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

// Load configuration from array
$container->loadConfiguration([
    'services' => [
        'primary.db' => [
            'class' => DatabaseConnection::class,
            'arguments' => [
                'dsn' => 'mysql://localhost:3306/mydb',
                'options' => [
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci'
                ]
            ],
            'singleton' => true,
            'group' => 'database',
            'tags' => ['database', 'primary'],
            'priority' => 100
        ],
        'secondary.db' => [
            'class' => DatabaseConnection::class,
            'arguments' => [
                'dsn' => 'mysql://localhost:3306/secondary',
                'options' => [
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci'
                ]
            ],
            'singleton' => true,
            'group' => 'database',
            'tags' => ['database', 'secondary'],
            'priority' => 90
        ],
        'user.repository' => [
            'class' => UserRepository::class,
            'arguments' => [
                'db' => '@primary.db' // Reference to primary database connection
            ],
            'singleton' => true,
            'group' => 'repository',
            'tags' => ['repository', 'user'],
            'priority' => 50
        ]
    ]
]);

// Use the services
$userRepo = $container->get('user.repository');
$user = $userRepo->findUser(1);
echo "Found user: " . json_encode($user) . "\n";

// Try to get the same service again (should use cached instance)
$userRepo2 = $container->get('user.repository');
$user2 = $userRepo2->findUser(2);
echo "Found user: " . json_encode($user2) . "\n";

// Get services by group
$databaseServices = $container->getServicesByGroup('database');
echo "Database services: " . implode(', ', $databaseServices) . "\n";

// Get services by tag
$repositoryServices = $container->getServicesByTag('repository');
echo "Repository services: " . implode(', ', $repositoryServices) . "\n"; 