<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\{Container, ServiceProvider};
use Scriptmancer\Kiler\Attributes\{Service, Inject};

// Define logging interface
interface LoggerInterface
{
    public function log(string $message): void;
}

// Firebase logger for mobile
#[Service(
    implements: LoggerInterface::class,
    group: 'mobile',
    tags: ['database'],
    priority: 100
)]
class FirebaseLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[Firebase] $message\n";
    }
}

// Sentry logger for web
#[Service(
    implements: LoggerInterface::class,
    group: 'web',
    tags: ['service'],
    priority: 50
)]
class SentryLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[Sentry] $message\n";
    }
}

// File logger as fallback
#[Service(
    implements: LoggerInterface::class,
    group: 'default',
    tags: ['file'],
    priority: 0
)]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[File] $message\n";
    }
}

// Bootstrap the application
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Register all loggers
$container->register(FirebaseLogger::class);
$container->register(SentryLogger::class);
$container->register(FileLogger::class);

// Example 1: Get mobile logger with database tag
echo "Mobile Logger (Database):\n";
$mobileLogger = $container->get(LoggerInterface::class, 'mobile', 'database');
$mobileLogger->log("User logged in from mobile app");

// Example 2: Get web logger with service tag
echo "\nWeb Logger (Service):\n";
$webLogger = $container->get(LoggerInterface::class, 'web', 'service');
$webLogger->log("User logged in from web app");

// Example 3: Get default logger (no group/tag specified)
echo "\nDefault Logger:\n";
$defaultLogger = $container->get(LoggerInterface::class);
$defaultLogger->log("User logged in");

// Example 4: Try to get a non-existent combination
echo "\nTrying to get non-existent combination:\n";
try {
    $logger = $container->get(LoggerInterface::class, 'mobile', 'service');
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Example 5: Service with multiple tags
#[Service(
    implements: LoggerInterface::class,
    group: 'hybrid',
    tags: ['database', 'service'],
    priority: 75
)]
class HybridLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[Hybrid] $message\n";
    }
}

$container->register(HybridLogger::class);

echo "\nHybrid Logger (Database):\n";
$hybridLogger = $container->get(LoggerInterface::class, 'hybrid', 'database');
$hybridLogger->log("User logged in from hybrid app"); 