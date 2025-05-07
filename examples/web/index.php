<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ScriptMancer\Kiler\{Container, ServiceProvider};
use ScriptMancer\Kiler\Attributes\{Service, Inject};

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

interface UserRepositoryInterface
{
    public function find(int $id): ?array;
    public function save(array $user): void;
}

// Implement services
#[Service(
    implements: LoggerInterface::class,
    group: 'core',
    tags: ['logging']
)]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        echo "<div style='color: gray;'>[LOG] $message</div>";
    }
}

#[Service(
    implements: CacheInterface::class,
    group: 'core',
    tags: ['caching']
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

#[Service(
    implements: UserRepositoryInterface::class,
    group: 'app',
    tags: ['repository']
)]
class UserRepository implements UserRepositoryInterface
{
    private array $users = [
        1 => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        2 => ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ];

    public function find(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }

    public function save(array $user): void
    {
        $this->users[$user['id']] = $user;
    }
}

// Define a service with constructor injection
#[Service(group: 'app')]
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly LoggerInterface $logger
    ) {}

    public function getUser(int $id): ?array
    {
        $this->logger->log("Fetching user $id");
        return $this->repository->find($id);
    }

    public function createUser(array $data): array
    {
        $this->logger->log("Creating new user: {$data['name']}");
        $this->repository->save($data);
        return $data;
    }
}

// Define a controller with both constructor and property injection
#[Service(group: 'app')]
class UserController
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    #[Inject]
    private ?CacheInterface $cache = null;

    #[Inject]
    private ?LoggerInterface $logger = null;

    public function showUser(int $id): string
    {
        $cacheKey = "user:$id";
        
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached) {
                $this->logger?->log("User $id retrieved from cache");
                return $this->formatUser($cached);
            }
        }

        $user = $this->userService->getUser($id);
        
        if (!$user) {
            return "User not found";
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, $user);
            $this->logger?->log("User $id cached");
        }

        return $this->formatUser($user);
    }

    private function formatUser(array $user): string
    {
        return "<div style='margin: 20px; padding: 10px; border: 1px solid #ccc;'>" .
               "<h3>{$user['name']}</h3>" .
               "<p>Email: {$user['email']}</p>" .
               "<form method='post'>" .
               "<input type='hidden' name='user_id' value='{$user['id']}'>" .
               "<input type='email' name='new_email' placeholder='New email'>" .
               "<button type='submit'>Update Email</button>" .
               "</form>" .
               "</div>";
    }

    public function updateUserEmail(int $userId, string $newEmail): string
    {
        $user = $this->userService->getUser($userId);
        if (!$user) {
            return "User not found";
        }

        $user['email'] = $newEmail;
        $this->userService->createUser($user);
        $this->logger?->log("Updated email for user {$user['name']} to {$newEmail}");

        return $this->formatUser($user);
    }
}

// Bootstrap the application
$container = Container::getInstance();
$provider = new ServiceProvider($container);

// Register services
$provider->registerService(FileLogger::class);
$provider->registerService(MemoryCache::class);
$provider->registerService(UserRepository::class);
$provider->registerService(UserService::class);
$provider->registerService(UserController::class);

// Handle request
$controller = $container->get(UserController::class);

// Handle POST request for email update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['new_email'])) {
    echo $container->callMethod($controller, 'updateUserEmail', [
        'userId' => (int)$_POST['user_id'],
        'newEmail' => $_POST['new_email']
    ]);
    exit;
}

// Get user ID from query string or default to 1
$userId = (int)($_GET['id'] ?? 1);

// Output HTML
echo "<!DOCTYPE html>
<html>
<head>
    <title>User Management Example</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .nav { margin: 20px 0; }
        .nav a { margin-right: 10px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>User Management Example</h1>
        <div class='nav'>
            <a href='?id=1'>User 1</a>
            <a href='?id=2'>User 2</a>
            <a href='?id=3'>User 3 (Not Found)</a>
        </div>
        " . $controller->showUser($userId) . "
    </div>
</body>
</html>"; 