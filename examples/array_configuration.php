<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ScriptMancer\Kiler\{Container, ServiceProvider};
use ScriptMancer\Kiler\Attributes\Service;

// Define interfaces
interface LoggerInterface
{
    public function log(string $message): void;
}

interface DatabaseInterface
{
    public function query(string $sql): array;
}

// Implement services
#[Service]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[File] $message\n";
    }
}

#[Service]
class FirebaseLogger implements LoggerInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $projectId
    ) {}

    public function log(string $message): void
    {
        echo "[Firebase] $message\n";
    }
}

#[Service]
class MySQLDatabase implements DatabaseInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database
    ) {}

    public function query(string $sql): array
    {
        echo "Executing query on MySQL: $sql\n";
        return ['result' => 'success'];
    }
}

// Define configuration
$config = [
    'services' => [
        // Basic service registration
        FileLogger::class => [
            'class' => FileLogger::class,
            'implements' => LoggerInterface::class,
            'group' => 'default',
            'tags' => ['logging'],
            'singleton' => true
        ],
        
        // Service with constructor parameters
        FirebaseLogger::class => [
            'class' => FirebaseLogger::class,
            'implements' => LoggerInterface::class,
            'group' => 'mobile',
            'tags' => ['database'],
            'arguments' => [
                'apiKey' => 'your-api-key',
                'projectId' => 'your-project-id'
            ]
        ],
        
        // Database service
        MySQLDatabase::class => [
            'class' => MySQLDatabase::class,
            'implements' => DatabaseInterface::class,
            'arguments' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'myapp'
            ]
        ]
    ]
];

// Bootstrap the application
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Load configuration
$container->loadConfiguration($config);

// Test the services
echo "Testing default logger:\n";
$logger = $container->get(LoggerInterface::class);
$logger->log("Hello from default logger");

echo "\nTesting Firebase logger:\n";
$firebaseLogger = $container->get(FirebaseLogger::class);
$firebaseLogger->log("Hello from Firebase");

echo "\nTesting database:\n";
$db = $container->get(DatabaseInterface::class);
$result = $db->query("SELECT * FROM users");
print_r($result); 