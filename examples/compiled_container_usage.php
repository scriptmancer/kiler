<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\ContainerCompiler;
use ScriptMancer\Kiler\Attributes\Service;

// Example services
#[Service]
class Database
{
    public function __construct(
        private readonly string $dsn = 'mysql:host=localhost;dbname=test',
        private readonly string $username = 'root',
        private readonly string $password = 'secret'
    ) {}

    public function connect(): void
    {
        echo "Connecting to database: {$this->dsn}\n";
    }
}

#[Service]
class UserRepository
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function findById(int $id): void
    {
        echo "Finding user with ID: $id\n";
        $this->db->connect();
    }
}

#[Service]
class UserService
{
    public function __construct(
        private readonly UserRepository $repository
    ) {}

    public function getUser(int $id): void
    {
        echo "Getting user...\n";
        $this->repository->findById($id);
    }
}

// Create cache directory if it doesn't exist
$cacheDir = __DIR__ . '/../var/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// Force development mode for this example
putenv('APP_ENV=dev');

// In development, use the dynamic container
if (getenv('APP_ENV') === 'dev') {
    echo "Development mode: Using dynamic container\n";
    
    $container = Container::getInstance();
    
    // Register services
    $container->register(Database::class);
    $container->register(UserRepository::class);
    $container->register(UserService::class);
    
    // Compile the container for production
    $compiler = new ContainerCompiler($cacheDir, 'ScriptMancer\\Kiler\\Compiled');
    $containerPath = $compiler->compile($container);
    
    echo "Container compiled for production use.\n";
    echo "Compiled container saved to: $containerPath\n";
    
    // Include the compiled container
    require_once $containerPath;
}

// Load the compiled container class
$containerClass = 'ScriptMancer\\Kiler\\Compiled\\Container';
if (!class_exists($containerClass)) {
    throw new RuntimeException("Compiled container class not found. Did you run in development mode first?");
}

// Use the compiled container
$container = new $containerClass();

// Use the container
$userService = $container->get(UserService::class);
$userService->getUser(1); 