<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler;

use Scriptmancer\Kiler\Event\EventDispatcherInterface;
use Scriptmancer\Kiler\Event\ServiceRegisteredEvent;
use Scriptmancer\Kiler\Event\ServiceResolvedEvent;
use Scriptmancer\Kiler\Exceptions\ContainerException;
use Scriptmancer\Kiler\Compiled\AbstractCompiledContainer;

class ContainerCompiler
{
    private string $cacheDir;
    private string $bootstrapDir;
    private string $namespace;

    public function __construct(string $cacheDir, string $namespace = 'Scriptmancer\\Kiler\\Compiled')
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->bootstrapDir = $this->cacheDir . '/bootstrap';
        $this->namespace = $namespace;
        $this->ensureDirectoriesExist();
    }

    private function ensureDirectoriesExist(): void
    {
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0777, true)) {
            throw new ContainerException("Could not create directory: {$this->cacheDir}");
        }

        if (!is_dir($this->bootstrapDir) && !mkdir($this->bootstrapDir, 0777, true)) {
            throw new ContainerException("Could not create directory: {$this->bootstrapDir}");
        }
    }

    public function compile(Container $container): string
    {
        $services = $container->getServices();
        $compiledContainer = $this->generateCompiledContainer($services);
        
        $containerPath = $this->bootstrapDir . '/Container.php';
        if (file_put_contents($containerPath, $compiledContainer) === false) {
            throw new ContainerException("Could not write compiled container to: {$containerPath}");
        }

        return $containerPath;
    }

    private function generateCompiledContainer(array $services): string
    {
        $servicesFile = $this->bootstrapDir . '/services.php';
        
        // Write services configuration
        $servicesContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $this->exportServices($services) . ";\n";
        if (file_put_contents($servicesFile, $servicesContent) === false) {
            throw new ContainerException("Could not write services configuration to: {$servicesFile}");
        }
        
        return "<?php\n\n" .
            "declare(strict_types=1);\n\n" .
            "namespace " . $this->namespace . ";\n\n" .
            "use Scriptmancer\\Kiler\\Compiled\\AbstractCompiledContainer;\n\n" .
            "class Container extends AbstractCompiledContainer\n" .
            "{\n" .
            "    protected function getServicesFilePath(): string\n" .
            "    {\n" .
            "        return __DIR__ . '/services.php';\n" .
            "    }\n" .
            "}\n";
    }

    private function exportServices(array $services): string
    {
        $export = ['services' => []];
        foreach ($services as $id => $definition) {
            $export['services'][$id] = [
                'class' => $definition['class'] ?? $id,
                'implements' => $definition['implements'] ?? null,
                'group' => $definition['group'] ?? null,
                'singleton' => $definition['singleton'] ?? true,
                'tags' => $definition['tags'] ?? [],
                'priority' => $definition['priority'] ?? 0,
                'arguments' => $definition['arguments'] ?? []
            ];

            // If this service implements an interface, register the interface as well
            if (isset($definition['implements']) && $definition['implements']) {
                $export['services'][$definition['implements']] = [
                    'class' => $definition['class'] ?? $id,
                    'implements' => null,
                    'group' => $definition['group'] ?? null,
                    'singleton' => $definition['singleton'] ?? true,
                    'tags' => $definition['tags'] ?? [],
                    'priority' => $definition['priority'] ?? 0,
                    'arguments' => $definition['arguments'] ?? []
                ];
            }
        }
        return var_export($export, true);
    }

    public function loadCompiledServices(): array
    {
        $cacheFile = $this->cacheDir . '/container.cache';
        if (!file_exists($cacheFile)) {
            return [];
        }

        $data = file_get_contents($cacheFile);
        if ($data === false) {
            return [];
        }

        $services = unserialize($data);
        return is_array($services) ? $services : [];
    }
} 