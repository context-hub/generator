<?php

declare(strict_types=1);

namespace Tests;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Application\Kernel;
use Spiral\Boot\Environment;
use Spiral\Core\Container;

abstract class AppTestCase extends TestCase
{
    private TestableKernelInterface&Kernel $app;

    public function defineDirectories(string $root): array
    {
        return [
            'root' => $root,
            'output' => $root . '/.context',
            'config' => $root,
            'json-schema' => $root,
        ];
    }

    public function rootDirectory(): string
    {
        return \dirname(path: __DIR__);
    }

    public function getApp(): TestableKernelInterface
    {
        if (!isset($this->app)) {
            $this->app = $this->initApp();
        }

        return $this->app;
    }

    public function createAppInstance(Container $container = new Container()): TestableKernelInterface
    {
        return TestApp::create(
            directories: $this->defineDirectories(
                $this->rootDirectory(),
            ),
            handleErrors: false,
            container: $container,
        );
    }

    /**
     * @param array<non-empty-string,mixed> $env
     */
    public function makeApp(array $env = [], Container $container = new Container()): Kernel&TestableKernelInterface
    {
        $environment = new Environment(values: $env);

        $app = $this->createAppInstance($container);
        $app->run($environment);

        return $app;
    }

    public function initApp(array $env = [], Container $container = new Container()): Kernel&TestableKernelInterface
    {
        $container->bindSingleton(
            alias: Application::class,
            resolver: new Application(
                version: '1.0.0',
                name: 'Context Generator',
                isBinary: true,
            ),
        );

        return $this->makeApp($env, $container);
    }

    public function getContainer(): Container
    {
        return $this->getApp()->getContainer();
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): mixed
    {
        return $this->getApp()->getContainer()->get(id: $id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->initApp();
    }
}
