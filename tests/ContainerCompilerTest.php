<?php

declare(strict_types=1);

namespace Scriptmancer\Kiler\Tests;

use PHPUnit\Framework\TestCase;
use Scriptmancer\Kiler\Container;
use Scriptmancer\Kiler\ContainerCompiler;
use Scriptmancer\Kiler\Exceptions\ContainerException;
use Scriptmancer\Kiler\Attributes\Service;

class ContainerCompilerTest extends TestCase
{
    private string $tempDir;
    private string $namespace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/kiler_test_' . uniqid();
        $this->namespace = 'TestContainer' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testCompileContainer(): void
    {
        $container = Container::getInstance();
        $compiler = new ContainerCompiler($this->tempDir, $this->namespace);

        // Register a test service
        $container->register(CompilerTestService::class);

        // Compile the container
        $containerPath = $compiler->compile($container);

        // Verify the compiled container exists
        $this->assertFileExists($containerPath);
    }

    public function testCompiledContainerWorks(): void
    {
        $container = Container::getInstance();
        $compiler = new ContainerCompiler($this->tempDir, $this->namespace);

        // Register a test service
        $container->register(CompilerTestService::class);

        // Compile the container
        $containerPath = $compiler->compile($container);

        // Load and use the compiled container
        require_once $containerPath;
        $containerClass = $this->namespace . '\\Container';
        $compiledContainer = new $containerClass();

        // Test that the service can be resolved
        $service = $compiledContainer->get(CompilerTestService::class);
        $this->assertInstanceOf(CompilerTestService::class, $service);
    }

    public function testCompiledContainerHandlesInterfaces(): void
    {
        $container = Container::getInstance();
        $compiler = new ContainerCompiler($this->tempDir, $this->namespace);

        // Register interface implementation
        $container->register(CompilerTestImplementation::class);

        // Compile the container
        $containerPath = $compiler->compile($container);

        // Load and use the compiled container
        require_once $containerPath;
        $containerClass = $this->namespace . '\\Container';
        $compiledContainer = new $containerClass();

        // Test that the interface can be resolved to the implementation
        $service = $compiledContainer->get(CompilerTestInterface::class);
        $this->assertInstanceOf(CompilerTestImplementation::class, $service);
    }
}

#[Service]
class CompilerTestService
{
}

#[Service]
interface CompilerTestInterface
{
    public function test(): string;
}

#[Service(implements: CompilerTestInterface::class)]
class CompilerTestImplementation implements CompilerTestInterface
{
    public function test(): string
    {
        return 'test';
    }
} 