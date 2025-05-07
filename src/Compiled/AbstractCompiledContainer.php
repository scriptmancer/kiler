<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler\Compiled;

use ScriptMancer\Kiler\Container;
use ScriptMancer\Kiler\Exceptions\ContainerException;
use ScriptMancer\Kiler\Event\EventDispatcherInterface;

abstract class AbstractCompiledContainer extends Container
{
    private ?EventDispatcherInterface $eventDispatcher = null;

    public function __construct()
    {
        parent::__construct(null);
        $this->loadCompiledServices();
    }

    abstract protected function getServicesFilePath(): string;

    private function loadCompiledServices(): void
    {
        $servicesFile = $this->getServicesFilePath();
        
        if (!file_exists($servicesFile)) {
            throw new ContainerException('Compiled services file not found. Did you forget to compile the container?');
        }

        $config = require $servicesFile;
        
        if (!is_array($config) || !isset($config['services'])) {
            throw new ContainerException('Invalid compiled services configuration');
        }

        $this->loadConfiguration($config);
    }

    public function register(string $class, ?string $alias = null, array $options = []): void
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