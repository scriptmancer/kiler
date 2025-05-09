<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Scriptmancer\Kiler\Container;
use Scriptmancer\Kiler\Attributes\{Service, Factory};

// Define interfaces
interface DatabaseInterface
{
    public function query(string $sql): array;
}

interface LoggerInterface
{
    public function log(string $message): void;
}

// Define services
#[Service(implements: DatabaseInterface::class)]
class MySQLDatabase implements DatabaseInterface
{
    public function query(string $sql): array
    {
        return ['result' => 'MySQL query result'];
    }
}

#[Service(implements: LoggerInterface::class)]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "Logging to file: $message\n";
    }
}

#[Service]
class UserService
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly LoggerInterface $logger
    ) {}

    public function getUsers(): array
    {
        $this->logger->log('Fetching users');
        return $this->db->query('SELECT * FROM users');
    }
}

// Create and configure container
$container = Container::getInstance();

// Register services
$container->register(MySQLDatabase::class);
$container->register(FileLogger::class);
$container->register(UserService::class);

// Use the container
$userService = $container->get(UserService::class);
$result = $userService->getUsers();

echo "Result: " . print_r($result, true) . "\n"; 