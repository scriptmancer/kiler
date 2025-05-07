<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ScriptMancer\Kiler\{Container, ServiceProvider};
use ScriptMancer\Kiler\Attributes\{Service, Inject};

// Define interfaces
interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

interface LoggerInterface
{
    public function log(string $message): void;
}

// Implement services
#[Service(
    implements: CacheInterface::class,
    group: 'core',
    tags: ['caching']
)]
class RedisCache implements CacheInterface
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

#[Service(
    implements: LoggerInterface::class,
    group: 'core',
    tags: ['logging']
)]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "[LOG] $message\n";
    }
}

// Service using property injection
#[Service(group: 'app')]
class UserService
{
    #[Inject]
    private ?CacheInterface $cache = null;

    #[Inject]
    private ?LoggerInterface $logger = null;

    public function getUser(int $id): ?array
    {
        $this->logger?->log("Fetching user $id");

        if ($this->cache) {
            $cached = $this->cache->get("user:$id");
            if ($cached) {
                $this->logger?->log("User $id found in cache");
                return $cached;
            }
        }

        // Simulate database lookup
        $user = ['id' => $id, 'name' => "User $id"];
        
        if ($this->cache) {
            $this->cache->set("user:$id", $user);
            $this->logger?->log("User $id cached");
        }

        return $user;
    }
}

// Bootstrap the application
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Register services
$provider->registerService(RedisCache::class);
$provider->registerService(FileLogger::class);
$provider->registerService(UserService::class);

// Use the service
$userService = $container->get(UserService::class);

// Get user (will use both cache and logger)
$user = $userService->getUser(1);
var_dump($user);

// Get same user again (should use cache)
$user = $userService->getUser(1);
var_dump($user); 