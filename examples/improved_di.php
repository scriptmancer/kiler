<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\{Container, ServiceProvider};
use Scriptmancer\Kiler\Attributes\{Service, Inject};

// Define interfaces
interface LoggerInterface
{
    public function log(string $message): void;
}

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

// Implement services with enhanced metadata
#[Service(
    implements: LoggerInterface::class,
    group: 'core',
    tags: ['logging', 'monitoring'],
    priority: 100
)]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[LOG] $message\n";
    }
}

#[Service(
    implements: CacheInterface::class,
    group: 'core',
    tags: ['caching'],
    priority: 90
)]
class MemoryCache implements CacheInterface
{
    private array $cache = [];

    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
    }
}

// Example service with automatic method injection
#[Service(group: 'app')]
class UserService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    // Method injection is automatic - no #[Inject] needed
    public function findUser(int $id, CacheInterface $cache): ?array
    {
        $this->logger->log("Finding user $id");
        
        // Try cache first
        $cached = $cache->get("user:$id");
        if ($cached) {
            $this->logger->log("User $id found in cache");
            return $cached;
        }

        // Simulate database lookup
        $user = ['id' => $id, 'name' => "User $id"];
        $cache->set("user:$id", $user);
        
        return $user;
    }
}

// Create service provider
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Register services
$provider->registerService(FileLogger::class);
$provider->registerService(MemoryCache::class);
$provider->registerService(UserService::class);

// Get services by group
echo "Core services:\n";
foreach ($provider->getServicesByGroup('core') as $service) {
    echo "- $service\n";
}

// Get services by tag
echo "\nCaching services:\n";
foreach ($provider->getServicesByTag('caching') as $service) {
    echo "- $service\n";
}

// Use the container
$userService = $container->get(UserService::class);

// Method injection is automatic using callMethod
// You can pass parameters by name
$user = $container->callMethod($userService, 'findUser', [
    'id' => 1
]);
var_dump($user);

// Or by position
$user = $container->callMethod($userService, 'findUser', [2]);
var_dump($user);

// Try again to see caching in action
$user = $container->callMethod($userService, 'findUser', ['id' => 1]);
var_dump($user);