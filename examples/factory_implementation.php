<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\{Container, ServiceProvider};
use Scriptmancer\Kiler\Attributes\Service;

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

// Implement services
#[Service]
class FileLogger implements LoggerInterface
{
    public function __construct(
        private readonly string $logPath
    ) {}

    public function log(string $message): void
    {
        echo "[File] $message (path: $this->logPath)\n";
    }
}

#[Service]
class RedisLogger implements LoggerInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port
    ) {}

    public function log(string $message): void
    {
        echo "[Redis] $message (host: $this->host:$this->port)\n";
    }
}

#[Service]
class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $cachePath
    ) {}

    public function get(string $key): mixed
    {
        echo "Getting from file cache: $key (path: $this->cachePath)\n";
        return null;
    }

    public function set(string $key, mixed $value): void
    {
        echo "Setting in file cache: $key (path: $this->cachePath)\n";
    }
}

#[Service]
class RedisCache implements CacheInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port
    ) {}

    public function get(string $key): mixed
    {
        echo "Getting from Redis: $key (host: $this->host:$this->port)\n";
        return null;
    }

    public function set(string $key, mixed $value): void
    {
        echo "Setting in Redis: $key (host: $this->host:$this->port)\n";
    }
}

// Bootstrap the application
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Register services
$container->register(FileLogger::class);
$container->register(RedisLogger::class);
$container->register(FileCache::class);
$container->register(RedisCache::class);

// Test the services
echo "Testing logger services:\n";
$fileLogger = new FileLogger('/custom/path/app.log');
$fileLogger->log("Hello from file logger");

$redisLogger = new RedisLogger('redis.example.com', 6380);
$redisLogger->log("Hello from Redis logger");

echo "\nTesting cache services:\n";
$fileCache = new FileCache('/custom/cache');
$fileCache->set('test', 'value');
$fileCache->get('test');

$redisCache = new RedisCache('redis.example.com', 6380);
$redisCache->set('test', 'value');
$redisCache->get('test'); 