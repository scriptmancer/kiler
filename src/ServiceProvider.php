<?php

declare(strict_types=1);

namespace ScriptMancer\Kiler;

class ServiceProvider
{
    private array $providers = [];
    private array $services = [];
    private array $groups = [];
    private array $tags = [];
    private array $booted = [];

    public function __construct(
        private readonly Container $container
    ) {}

    public function registerProvider(string $providerClass): void
    {
        if (!isset($this->providers[$providerClass])) {
            $provider = new $providerClass($this->container);
            $this->providers[$providerClass] = $provider;
            $provider->register();
        }
    }

    public function boot(): void
    {
        foreach ($this->providers as $providerClass => $provider) {
            if (!isset($this->booted[$providerClass])) {
                $provider->boot();
                $this->booted[$providerClass] = true;
            }
        }
    }

    public function getRegisteredProviders(): array
    {
        return array_keys($this->providers);
    }

    public function isProviderRegistered(string $providerClass): bool
    {
        return isset($this->providers[$providerClass]);
    }

    public function isProviderBooted(string $providerClass): bool
    {
        return isset($this->booted[$providerClass]);
    }

    public function registerService(string $serviceClass, array $options = []): void
    {
        $this->services[$serviceClass] = $options;
        
        // Register with container using alias if provided
        $alias = $options['alias'] ?? null;
        $this->container->register($serviceClass, $alias);

        // Group services
        if (isset($options['group'])) {
            $this->groups[$options['group']][] = $serviceClass;
        }

        // Tag services
        if (isset($options['tags'])) {
            foreach ($options['tags'] as $tag) {
                $this->tags[$tag][] = $serviceClass;
            }
        }
    }

    public function getServicesByGroup(string $group): array
    {
        return $this->groups[$group] ?? [];
    }

    public function getServicesByTag(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    public function getServicesByPriority(): array
    {
        $services = $this->services;
        uasort($services, function($a, $b) {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });
        return array_keys($services);
    }
} 