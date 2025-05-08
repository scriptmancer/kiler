# Kiler - Dependency Injection Container

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Kiler is a lightweight, feature-rich dependency injection container for PHP applications. It provides a simple yet powerful way to manage dependencies and services in your application.

## Features

- 🚀 Simple and intuitive API
- 🔄 Support for both dynamic and compiled containers
- 🏷️ Service registration with attributes
- 🔌 Interface-based dependency injection
- 🏷️ Service tagging and grouping
- 🔒 Singleton and factory services
- 📦 PSR-11 compliant
- 🧪 Comprehensive test coverage
- 🚀 Production-ready with compiled container support

## Installation

```bash
composer require scriptmancer/kiler
```

## Quick Start

```php
use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\Attributes\Service;

// Define a service
#[Service]
class Database
{
    public function __construct(
        private readonly string $dsn = 'mysql:host=localhost;dbname=test'
    ) {}
}

// Get container instance
$container = Container::getInstance();

// Register service
$container->register(Database::class);

// Use service
$db = $container->get(Database::class);
```

## Service Registration

### Using Attributes

```php
use ScriptMancer\Kiler\Attributes\Service;

#[Service(
    implements: DatabaseInterface::class,
    group: 'database',
    tags: ['storage', 'persistence'],
    singleton: true
)]
class Database implements DatabaseInterface
{
    // ...
}
```

### Manual Registration

```php
$container->register(Database::class, 'db');
$container->registerFactory('db.factory', fn() => new Database());
```

## Interface Resolution

```php
interface LoggerInterface
{
    public function log(string $message): void;
}

#[Service(implements: LoggerInterface::class)]
class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
        // ...
    }
}

// Resolve interface to implementation
$logger = $container->get(LoggerInterface::class);
```

## Service Groups and Tags

```php
// Register service with group and tags
$container->register(Database::class);

// Get all services in a group
$databaseServices = $container->getServicesByGroup('database');

// Get all services with a tag
$storageServices = $container->getServicesByTag('storage');
```

## Compiled Container

For production environments, Kiler provides a compiled container that improves performance:

```php
use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\ContainerCompiler;

// In development
$container = Container::getInstance();
$container->register(Database::class);

// Compile container
$compiler = new ContainerCompiler('/path/to/cache', 'App\\Bootstrap');
$containerPath = $compiler->compile($container);

// In production
$container = new App\Bootstrap\Container();
$db = $container->get(Database::class);
```

## Event System

Kiler integrates with event systems through the `EventDispatcherInterface`:

```php
use ScriptMancer\Kiler\Event\EventDispatcherInterface;

$container->setEventDispatcher($eventDispatcher);
```

Available events:
- `container.service.registered`: Fired when a service is registered
- `container.service.resolved`: Fired when a service is resolved

## Service Providers

Register service providers to configure services:

```php
use ScriptMancer\Kiler\Interfaces\ServiceProviderInterface;

class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->register(Database::class);
    }

    public function getPriority(): int
    {
        return 0;
    }
}

$container->addServiceProvider(new DatabaseServiceProvider());
$container->registerProviders();
```

## Best Practices

1. Use attributes for service configuration
2. Register services in service providers
3. Use interface-based dependency injection
4. Compile container for production
5. Use groups and tags for service organization
6. Keep services stateless when possible

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Kiler is part of the [Nazım Framework](https://github.com/scriptmancer/nazim) project. 