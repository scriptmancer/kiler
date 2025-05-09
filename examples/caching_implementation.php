<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\{Container, ServiceProvider};
use Scriptmancer\Kiler\Cache\FileCache;
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
    public function query(string $sql): array
    {
        echo "Executing query: $sql\n";
        return ['result' => 'success'];
    }
}

// Create cache implementation
$cache = new FileCache(__DIR__ . '/cache');

// Bootstrap the application with cache
$container = Container::getInstance($cache);

// Register services
$container->register(FileLogger::class);
$container->register(MySQLDatabase::class);

// Test services
echo "\nTesting services:\n";
$logger = $container->get(LoggerInterface::class);
$database = $container->get(DatabaseInterface::class);

$logger->log('Testing logger with container-managed cache');
$database->query('SELECT * FROM users'); 