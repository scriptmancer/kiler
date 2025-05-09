<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\{Container, ServiceProvider, LazyService};
use Scriptmancer\Kiler\Attributes\Service;

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
#[Service(implements: LoggerInterface::class)]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[File] $message\n";
    }
}

#[Service(implements: DatabaseInterface::class)]
class MySQLDatabase implements DatabaseInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function query(string $sql): array
    {
        $this->logger->log("Executing query: $sql");
        return ['result' => 'success'];
    }
}

// Service that uses lazy loading
class UserService
{
    public function __construct(
        private readonly LazyService $logger,
        private readonly LazyService $database
    ) {}

    public function findUser(int $id): array
    {
        // Logger is only instantiated when first used
        $this->logger->get()->log("Finding user $id");
        
        // Database is only instantiated when first used
        return $this->database->get()->query("SELECT * FROM users WHERE id = $id");
    }
}

// Bootstrap the application
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Register services
$container->register(FileLogger::class);
$container->register(MySQLDatabase::class);

// Create lazy services
$lazyLogger = new LazyService($container, LoggerInterface::class);
$lazyDatabase = new LazyService($container, DatabaseInterface::class);

// Create user service with lazy dependencies
$userService = new UserService($lazyLogger, $lazyDatabase);

// Test the service
echo "Testing user service:\n";
$result = $userService->findUser(1);
print_r($result);

// Test that services are only instantiated once
echo "\nTesting singleton behavior:\n";
$result = $userService->findUser(2);
print_r($result); 