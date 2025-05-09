<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Compiled;

use Scriptmancer\Kiler\Container;
use Scriptmancer\Kiler\Exceptions\ContainerException;
use Scriptmancer\Kiler\Event\EventDispatcherInterface;

class CompiledContainer extends Container
{
    private ?EventDispatcherInterface $eventDispatcher = null;

    public function __construct()
    {
        parent::__construct(null);
        $this->loadCompiledServices();
    }

    private function loadCompiledServices(): void
    {
        $servicesFile = dirname(__DIR__, 2) . '/var/cache/container_services.php';
        
        if (!file_exists($servicesFile)) {
            throw new ContainerException('Compiled services file not found. Did you forget to compile the container?');
        }

        $config = require $servicesFile;
        
        if (!is_array($config) || !isset($config['services'])) {
            throw new ContainerException('Invalid compiled services configuration');
        }

        $this->loadConfiguration($config);
    }

    public function register(string $class, ?string $alias = null): void
    {
        throw new ContainerException('Cannot register services in compiled container');
    }

    public function registerFactory(string $id, callable $factory): void
    {
        throw new ContainerException('Cannot register factories in compiled container');
    }

    public function clear(): void
    {
        throw new ContainerException('Cannot clear compiled container');
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
} 