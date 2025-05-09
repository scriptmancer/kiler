<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Scriptmancer\Kiler\{Container, ServiceProvider};
use Scriptmancer\Kiler\Attributes\{Service, Inject};

// Define core interfaces
interface LoggerInterface
{
    public function log(string $message): void;
}

interface ConfigInterface
{
    public function get(string $key, mixed $default = null): mixed;
}

interface DatabaseInterface
{
    public function query(string $sql): array;
}

// Implement core services
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

#[Service(
    implements: ConfigInterface::class,
    group: 'core',
    tags: ['config']
)]
class Config implements ConfigInterface
{
    private array $config = [
        'app.name' => 'My Application',
        'app.debug' => true,
        'database.host' => 'localhost',
        'database.name' => 'myapp',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}

#[Service(
    implements: DatabaseInterface::class,
    group: 'core',
    tags: ['database']
)]
class Database implements DatabaseInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {}

    public function query(string $sql): array
    {
        $this->logger->log("Executing query: $sql");
        // Simulate database query
        return ['result' => 'success'];
    }
}

// Define application services
#[Service(group: 'app')]
class UserService
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly LoggerInterface $logger
    ) {}

    public function findUser(int $id): array
    {
        $this->logger->log("Finding user $id");
        return $this->db->query("SELECT * FROM users WHERE id = $id");
    }
}

#[Service(group: 'app')]
class ProductService
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly LoggerInterface $logger
    ) {}

    public function findProduct(int $id): array
    {
        $this->logger->log("Finding product $id");
        return $this->db->query("SELECT * FROM products WHERE id = $id");
    }
}

// Bootstrap the application
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Register core services
$provider->registerService(FileLogger::class);
$provider->registerService(Config::class);
$provider->registerService(Database::class);

// Register application services
$provider->registerService(UserService::class);
$provider->registerService(ProductService::class);

// Use the container
$userService = $container->get(UserService::class);
$productService = $container->get(ProductService::class);

// Test the services
echo "Testing User Service:\n";
$user = $userService->findUser(1);
print_r($user);

echo "\nTesting Product Service:\n";
$product = $productService->findProduct(1);
print_r($product);

// Test method injection
echo "\nTesting Method Injection:\n";
$result = $container->callMethod($userService, 'findUser', ['id' => 2]);
print_r($result); 