<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ScriptMancer\Kiler\Attributes\{Service, Inject};
use ScriptMancer\Kiler\Exceptions\{ContainerException, NotFoundException};
use ScriptMancer\Kiler\Cache\ContainerCacheInterface;
use ScriptMancer\Kiler\Event\{EventDispatcherInterface, ServiceRegisteredEvent, ServiceResolvedEvent};
use Psr\Container\ContainerInterface;
use ScriptMancer\Kiler\Interfaces\ServiceFactoryInterface;
use ScriptMancer\Kiler\Interfaces\ServiceProviderInterface;

class Container implements ContainerInterface
{
    public const CACHE_KEY = 'container_services';
    private const CACHE_VERSION = '1.0';
    private const CACHE_TTL = 3600; // 1 hour

    private static ?Container $instance = null;
    protected array $services = [];
    private array $factories = [];
    private array $instances = [];
    private array $groups = [];
    private array $tags = [];
    private array $registrationOrder = [];
    private array $aliases = [];
    private ?ContainerCacheInterface $cache;
    private ?EventDispatcherInterface $eventDispatcher = null;
    private array $resolutionStack = [];
    private array $serviceFactories = [];
    private array $serviceProviders = [];

    protected function __construct(?ContainerCacheInterface $cache = null)
    {
        $this->cache = $cache;
        $this->services = [];
        $this->factories = [];
        $this->instances = [];
        $this->groups = [];
        $this->tags = [];
        $this->registrationOrder = [];
        $this->aliases = [];
        
        if ($this->cache !== null) {
            $this->loadFromCache();
        }
    }

    public static function getInstance(?ContainerCacheInterface $cache = null): Container
    {
        if (self::$instance === null || (self::$instance->cache !== $cache)) {
            self::$instance = new self($cache);
        }
        return self::$instance;
    }

    private function loadFromCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            if (!$this->cache->has(self::CACHE_KEY)) {
                $this->services = [];
                return;
            }

            $cachedData = $this->cache->get(self::CACHE_KEY);
            if (!is_array($cachedData) || !isset($cachedData['version']) || !isset($cachedData['services'])) {
                $this->services = [];
                $this->cache->delete(self::CACHE_KEY);
                return;
            }

            if ($cachedData['version'] !== self::CACHE_VERSION) {
                $this->services = [];
                $this->cache->delete(self::CACHE_KEY);
                return;
            }

            $this->services = $cachedData['services'];
        } catch (NotFoundException $e) {
            $this->services = [];
        }
    }

    private function saveToCache(): void
    {
        if ($this->cache === null) {
            return;
        }

        $data = [
            'version' => self::CACHE_VERSION,
            'services' => $this->services
        ];

        $this->cache->set(self::CACHE_KEY, $data, self::CACHE_TTL);
    }

    public function clearCache(): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
    }

    public function register(string $class, ?string $alias = null, array $options = []): void
    {
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(Service::class);
        
        if (empty($attributes)) {
            throw new ContainerException("Class $class must be marked with #[Service] attribute");
        }

        $service = $attributes[0]->newInstance();
        $serviceInfo = [
            'class' => $class,
            'implements' => $service->implements,
            'group' => $service->group,
            'singleton' => $service->singleton,
            'tags' => $service->tags ?? [],
            'priority' => $service->priority ?? 0,
            'arguments' => $options['arguments'] ?? []
        ];

        // Use the service ID from attribute if provided, otherwise use the class name
        $serviceId = $service->id ?? $class;
        
        // Store service info under service ID
        $this->services[$serviceId] = $serviceInfo;
        
        // Also store under class name for direct class resolution
        $this->services[$class] = $serviceInfo;

        // Store alias if provided (either from parameter or attribute)
        if ($alias !== null) {
            $this->aliases[$alias] = $serviceId;
        }

        // Store group information
        if ($service->group) {
            $this->groups[$service->group][] = $serviceId;
        }

        // Store tag information
        if (!empty($service->tags)) {
            foreach ($service->tags as $tag) {
                $this->tags[$tag][] = $serviceId;
            }
        }

        // Store registration order
        $this->registrationOrder[] = $serviceId;

        // Register for explicitly specified interface
        if ($service->implements) {
            $this->registerForInterface($service->implements, $serviceId, $serviceInfo);
        }

        // Register for all implemented interfaces that have the Service attribute
        $interfaces = $reflection->getInterfaces();
        foreach ($interfaces as $interface) {
            $interfaceName = $interface->getName();
            if ($interfaceName !== $service->implements) { // Skip if already registered
                $interfaceAttributes = $interface->getAttributes(Service::class);
                if (!empty($interfaceAttributes)) {
                    $this->registerForInterface($interfaceName, $serviceId, $serviceInfo);
                }
            }
        }

        // Save updated configuration to cache
        $this->saveToCache();

        // Dispatch service registered event
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch('container.service.registered', [
                'event' => new ServiceRegisteredEvent(
                    serviceId: $serviceId,
                    serviceClass: $class,
                    alias: $alias,
                    group: $service->group,
                    tags: $service->tags ?? [],
                    singleton: $service->singleton,
                    implements: $service->implements
                )
            ]);
        }
    }

    private function registerForInterface(string $interfaceName, string $serviceId, array $serviceInfo): void
    {
        // Create a copy of service info for the interface
        $interfaceInfo = $serviceInfo;
        $interfaceInfo['class'] = $serviceInfo['class']; // Keep the concrete class reference
        $this->services[$interfaceName] = $interfaceInfo;
        
        // Add the interface to the same groups and tags
        if ($serviceInfo['group']) {
            $this->groups[$serviceInfo['group']][] = $interfaceName;
        }
        if (!empty($serviceInfo['tags'])) {
            foreach ($serviceInfo['tags'] as $tag) {
                $this->tags[$tag][] = $interfaceName;
            }
        }
    }

    public function get(string $id, ?string $group = null, ?string $tag = null): object
    {
        // Check if the ID is an alias
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        // If no group or tag specified, use standard resolution
        if ($group === null && $tag === null) {
            return $this->resolveService($id);
        }

        // Find matching services
        $matchingServices = $this->findMatchingServices($id, $group, $tag);

        if (empty($matchingServices)) {
            throw new NotFoundException(
                sprintf(
                    "No service found for id '%s' with group '%s' and tag '%s'",
                    $id,
                    $group ?? 'any',
                    $tag ?? 'any'
                )
            );
        }

        // If multiple services match, use the one with highest priority
        $highestPriority = -1;
        $selectedService = null;

        foreach ($matchingServices as $serviceClass) {
            $priority = $this->services[$serviceClass]['priority'] ?? 0;
            if ($priority > $highestPriority) {
                $highestPriority = $priority;
                $selectedService = $serviceClass;
            }
        }

        return $this->resolveService($selectedService);
    }

    private function findMatchingServices(string $id, ?string $group, ?string $tag): array
    {
        $matchingServices = [];

        // First, find all services that implement the interface or match the class
        $candidates = [];
        
        // If the ID is an interface, look for all services that implement it
        if (interface_exists($id)) {
            foreach ($this->services as $serviceClass => $serviceInfo) {
                if ($serviceInfo['implements'] === $id) {
                    $candidates[] = $serviceClass;
                }
            }
        } else {
            // If it's a concrete class, just check that class
            if (isset($this->services[$id])) {
                $candidates[] = $id;
            }
        }

        foreach ($candidates as $candidate) {
            $service = $this->services[$candidate];
            
            // Check group match
            if ($group !== null && $service['group'] !== $group) {
                continue;
            }

            // Check tag match
            if ($tag !== null && !in_array($tag, $service['tags'], true)) {
                continue;
            }

            $matchingServices[] = $candidate;
        }

        return $matchingServices;
    }

    private function formatDependencyValue($value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_object($value)) {
            return get_class($value);
        }
        return (string)$value;
    }

    private function resolveService(string $id): object
    {
        // Check for circular dependency
        if (in_array($id, $this->resolutionStack)) {
            $path = implode(' -> ', array_merge($this->resolutionStack, [$id]));
            throw new ContainerException("Circular dependency detected: $path");
        }

        // Add current service to resolution stack
        $this->resolutionStack[] = $id;

        try {
            // If the ID is an alias, resolve it
            if (isset($this->aliases[$id])) {
                $id = $this->aliases[$id];
            }

            // If the ID is an interface, get its implementation class
            if (interface_exists($id)) {
                if (!isset($this->services[$id])) {
                    throw new NotFoundException("No implementation found for interface $id");
                }
                $id = $this->services[$id]['class'];
            }

            // If the service ID is not a class name, try to find it in services
            if (!class_exists($id) && isset($this->services[$id])) {
                $id = $this->services[$id]['class'];
            }

            if (!isset($this->services[$id])) {
                throw new NotFoundException("Service $id not found");
            }

            $service = $this->services[$id];
            
            // Check if this is a factory service
            if (isset($service['factory'])) {
                $instance = $service['factory']();
                
                if ($this->eventDispatcher !== null) {
                    $this->eventDispatcher->dispatch('container.service.resolved', [
                        'event' => new ServiceResolvedEvent(
                            serviceId: $id,
                            instance: $instance,
                            fromCache: false,
                            dependencies: []
                        )
                    ]);
                }
                
                // Remove from stack after successful resolution
                array_pop($this->resolutionStack);
                return $instance;
            }

            // Check if we have a service factory that can create this service
            foreach ($this->serviceFactories as $factory) {
                if ($factory->supports($id)) {
                    $instance = $factory->createService($this, $id, $service['arguments'] ?? []);
                    
                    if ($this->eventDispatcher !== null) {
                        $this->eventDispatcher->dispatch('container.service.resolved', [
                            'event' => new ServiceResolvedEvent(
                                serviceId: $id,
                                instance: $instance,
                                fromCache: false,
                                dependencies: []
                            )
                        ]);
                    }
                    
                    // Remove from stack after successful resolution
                    array_pop($this->resolutionStack);
                    return $instance;
                }
            }
            
            // Check if we already have an instance (singleton)
            if ($service['singleton'] && isset($this->instances[$id])) {
                if ($this->eventDispatcher !== null) {
                    $this->eventDispatcher->dispatch('container.service.resolved', [
                        'event' => new ServiceResolvedEvent(
                            serviceId: $id,
                            instance: $this->instances[$id],
                            fromCache: true,
                            dependencies: $service['arguments'] ?? []
                        )
                    ]);
                }
                
                // Remove from stack after successful resolution
                array_pop($this->resolutionStack);
                return $this->instances[$id];
            }

            // Get constructor dependencies
            $reflection = new ReflectionClass($service['class']);
            $dependencies = [];
            
            if ($reflection->hasMethod('__construct')) {
                $constructor = $reflection->getMethod('__construct');
                $dependencies = $this->resolveDependencies($constructor->getParameters(), $service['arguments'] ?? []);
            }

            $instance = $reflection->newInstanceArgs($dependencies);
            $this->resolveProperties($reflection, $instance);
            
            // Store instance if singleton
            if ($service['singleton']) {
                $this->instances[$id] = $instance;
            }

            if ($this->eventDispatcher !== null) {
                $this->eventDispatcher->dispatch('container.service.resolved', [
                    'event' => new ServiceResolvedEvent(
                        serviceId: $id,
                        instance: $instance,
                        fromCache: false,
                        dependencies: array_map(
                            fn($dep) => $this->formatDependencyValue($dep),
                            $dependencies
                        )
                    )
                ]);
            }

            // Remove from stack after successful resolution
            array_pop($this->resolutionStack);
            return $instance;
        } catch (\Throwable $e) {
            // Remove from stack on error
            array_pop($this->resolutionStack);
            throw $e;
        }
    }

    public function has(string $id): bool
    {
        // Check both direct service and alias
        return isset($this->services[$id]) || isset($this->aliases[$id]);
    }

    public function hasServiceInGroup(string $group, string $class): bool
    {
        return isset($this->groups[$group]) && in_array($class, $this->groups[$group], true);
    }

    public function hasServiceWithTag(string $tag, string $class): bool
    {
        return isset($this->tags[$tag]) && in_array($class, $this->tags[$tag], true);
    }

    public function getServicesByGroup(string $group): array
    {
        return $this->groups[$group] ?? [];
    }

    public function getServicesByTag(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    public function getServiceRegistrationOrder(): array
    {
        return $this->registrationOrder;
    }

    private function resolve(string $id, array $providedArgs = []): object
    {
        // Check for circular dependency
        if (in_array($id, $this->resolutionStack)) {
            $path = implode(' -> ', array_merge($this->resolutionStack, [$id]));
            throw new ContainerException("Circular dependency detected: $path");
        }

        // Add current service to resolution stack
        $this->resolutionStack[] = $id;

        try {
            $reflection = new ReflectionClass($id);
            $dependencies = [];
            
            if ($reflection->hasMethod('__construct')) {
                $constructor = $reflection->getMethod('__construct');
                $dependencies = $this->resolveDependencies($constructor->getParameters(), $providedArgs);
            }
            
            $instance = $reflection->newInstanceArgs($dependencies);
            $this->resolveProperties($reflection, $instance);
            
            // Remove from stack after successful resolution
            array_pop($this->resolutionStack);
            return $instance;
        } catch (\Throwable $e) {
            // Remove from stack on error
            array_pop($this->resolutionStack);
            throw $e;
        }
    }

    private function resolveConstructor(ReflectionClass $reflection, array $providedArgs = []): object
    {
        if (!$reflection->hasMethod('__construct')) {
            return $reflection->newInstance();
        }

        $constructor = $reflection->getMethod('__construct');
        $dependencies = $this->resolveDependencies($constructor->getParameters(), $providedArgs);
        
        return $reflection->newInstanceArgs($dependencies);
    }

    private function resolveProperties(ReflectionClass $reflection, object $instance): void
    {
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Inject::class);
            
            if (empty($attributes)) {
                continue;
            }

            $type = $property->getType();
            if (!$type || $type->isBuiltin()) {
                continue;
            }

            $property->setAccessible(true);

            // Special handling for EventDispatcherInterface
            if ($type->getName() === EventDispatcherInterface::class) {
                if ($this->eventDispatcher === null) {
                    throw new ContainerException("Event dispatcher not set in container");
                }
                $property->setValue($instance, $this->eventDispatcher);
                continue;
            }

            $property->setValue($instance, $this->get($type->getName()));
        }
    }

    private function resolveDependencies(array $parameters, array $providedArgs = []): array
    {
        $dependencies = [];
        $positionalIndex = 0;

        foreach ($parameters as $param) {
            // Check for named parameter
            if (isset($providedArgs[$param->getName()])) {
                $value = $providedArgs[$param->getName()];
                
                // Handle service references (starting with @)
                if (is_string($value) && str_starts_with($value, '@')) {
                    $serviceId = substr($value, 1);
                    $dependencies[] = $this->get($serviceId);
                    continue;
                }
                
                $dependencies[] = $value;
                continue;
            }

            // Check for positional parameter
            if (isset($providedArgs[$positionalIndex])) {
                $value = $providedArgs[$positionalIndex];
                
                // Handle service references (starting with @)
                if (is_string($value) && str_starts_with($value, '@')) {
                    $serviceId = substr($value, 1);
                    $dependencies[] = $this->get($serviceId);
                    $positionalIndex++;
                    continue;
                }
                
                $dependencies[] = $value;
                $positionalIndex++;
                continue;
            }

            // Try to resolve as a service
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
                continue;
            }

            // Use default value if available
            if ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
                continue;
            }

            throw new ContainerException("Cannot resolve parameter {$param->getName()}");
        }

        return $dependencies;
    }

    public function callMethod(object $instance, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($instance, $method);
        $dependencies = $this->resolveDependencies($reflection->getParameters(), $args);
        return $reflection->invokeArgs($instance, $dependencies);
    }

    public function loadConfiguration(array $config): void
    {
        if (isset($config['services'])) {
            foreach ($config['services'] as $id => $serviceConfig) {
                $class = $serviceConfig['class'] ?? $id;
                $alias = $serviceConfig['alias'] ?? null;
                $group = $serviceConfig['group'] ?? null;
                $tags = $serviceConfig['tags'] ?? [];
                $singleton = $serviceConfig['singleton'] ?? true;
                $priority = $serviceConfig['priority'] ?? 0;
                $implements = $serviceConfig['implements'] ?? null;
                $arguments = $serviceConfig['arguments'] ?? [];

                // Create service info
                $serviceInfo = [
                    'class' => $class,
                    'implements' => $implements,
                    'group' => $group,
                    'singleton' => $singleton,
                    'tags' => $tags,
                    'priority' => $priority,
                    'arguments' => $arguments
                ];

                // Store service info under service ID
                $this->services[$id] = $serviceInfo;
                
                // Also store under class name for direct class resolution
                $this->services[$class] = $serviceInfo;

                // Store alias if provided
                if ($alias !== null) {
                    $this->aliases[$alias] = $id;
                }

                // Store group information
                if ($group) {
                    $this->groups[$group][] = $id;
                }

                // Store tag information
                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        $this->tags[$tag][] = $id;
                    }
                }

                // Store registration order
                $this->registrationOrder[] = $id;

                // If this service implements an interface, store it under the interface name too
                if ($implements) {
                    // Create a copy of service info for the interface
                    $interfaceInfo = $serviceInfo;
                    $interfaceInfo['class'] = $class; // Keep the concrete class reference
                    $this->services[$implements] = $interfaceInfo;
                    
                    // Add the interface to the same groups and tags
                    if ($group) {
                        $this->groups[$group][] = $implements;
                    }
                    if (!empty($tags)) {
                        foreach ($tags as $tag) {
                            $this->tags[$tag][] = $implements;
                        }
                    }
                }
            }

            // Save loaded configuration to cache
            $this->saveToCache();
        }
    }

    public function registerFactory(string $id, callable $factory): void
    {
        $this->services[$id] = [
            'class' => $id,
            'factory' => $factory,
            'singleton' => false
        ];
    }

    public function getServices(): array
    {
        return $this->services;
    }

    public function clear(): void
    {
        $this->services = [];
        $this->factories = [];
        $this->instances = [];
        $this->groups = [];
        $this->tags = [];
        $this->registrationOrder = [];
        $this->aliases = [];
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function addServiceFactory(ServiceFactoryInterface $factory): void
    {
        $this->serviceFactories[] = $factory;
    }

    public function addServiceProvider(ServiceProviderInterface $provider): void
    {
        $this->serviceProviders[] = $provider;
        // Sort providers by priority and dependencies
        usort($this->serviceProviders, function(ServiceProviderInterface $a, ServiceProviderInterface $b) {
            // First sort by priority
            $priorityDiff = $b->getPriority() <=> $a->getPriority();
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }
            
            // Then check dependencies
            $aDeps = $a->getDependencies();
            $bDeps = $b->getDependencies();
            
            // If A depends on B, B should come first
            if (in_array(get_class($b), $aDeps)) {
                return 1;
            }
            
            // If B depends on A, A should come first
            if (in_array(get_class($a), $bDeps)) {
                return -1;
            }
            
            return 0;
        });
    }

    public function registerProviders(): void
    {
        // First register all services
        foreach ($this->serviceProviders as $provider) {
            $provider->register($this);
        }
        
        // Then boot them in order
        foreach ($this->serviceProviders as $provider) {
            $provider->boot($this);
        }
    }
} 