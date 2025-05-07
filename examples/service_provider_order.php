<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\Interfaces\ServiceProviderInterface;
use ScriptMancer\Kiler\Attributes\Service;

// Example services
#[Service(id: 'database', priority: 10)]
class DatabaseService {
    public function __construct() {
        echo "Database service initialized\n";
    }
}

#[Service(id: 'cache', priority: 5)]
class CacheService {
    public function __construct(DatabaseService $db) {
        echo "Cache service initialized\n";
    }
}

#[Service(id: 'logging', priority: 0)]
class LoggingService {
    public function __construct(DatabaseService $db, CacheService $cache) {
        echo "Logging service initialized\n";
    }
}

// Service providers
class DatabaseProvider implements ServiceProviderInterface {
    public function register(Container $container): void {
        $container->register(DatabaseService::class);
    }

    public function getPriority(): int {
        return 10; // High priority
    }

    public function getDependencies(): array {
        return []; // No dependencies
    }

    public function boot(Container $container): void {
        echo "Database provider booted\n";
    }
}

class CacheProvider implements ServiceProviderInterface {
    public function register(Container $container): void {
        $container->register(CacheService::class);
    }

    public function getPriority(): int {
        return 5; // Medium priority
    }

    public function getDependencies(): array {
        return [DatabaseProvider::class]; // Depends on DatabaseProvider
    }

    public function boot(Container $container): void {
        echo "Cache provider booted\n";
    }
}

class LoggingProvider implements ServiceProviderInterface {
    public function register(Container $container): void {
        $container->register(LoggingService::class);
    }

    public function getPriority(): int {
        return 0; // Low priority
    }

    public function getDependencies(): array {
        return [DatabaseProvider::class, CacheProvider::class]; // Depends on both
    }

    public function boot(Container $container): void {
        echo "Logging provider booted\n";
    }
}

// Create container and register providers
$container = Container::getInstance();

// Register providers in any order - they will be sorted by priority and dependencies
$container->addServiceProvider(new LoggingProvider());
$container->addServiceProvider(new DatabaseProvider());
$container->addServiceProvider(new CacheProvider());

// Initialize all services
$container->registerProviders();

// Get services to verify they were initialized correctly
$logging = $container->get(LoggingService::class); 