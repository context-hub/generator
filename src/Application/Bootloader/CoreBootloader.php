<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Console\GenerateCommand;
use Butschster\ContextGenerator\Console\SchemaCommand;
use Butschster\ContextGenerator\Console\SelfUpdateCommand;
use Butschster\ContextGenerator\Console\VersionCommand;
use Butschster\ContextGenerator\Directories;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\Config\Proxy;
use Spiral\Files\Files;
use Spiral\Files\FilesInterface;

final class CoreBootloader extends Bootloader
{
    private bool $init = false;

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            FilesInterface::class => Files::class,
            \Butschster\ContextGenerator\DirectoriesInterface::class => new Proxy(
                interface: \Butschster\ContextGenerator\DirectoriesInterface::class,
                fallbackFactory: static function (ContainerInterface $container) {
                    $dirs = $container->get(DirectoriesInterface::class);

                    $jsonSchemaPath = $dirs->get('json-schema');

                    return new Directories(
                        rootPath: $dirs->get('root'),
                        outputPath: $dirs->get('output'),
                        configPath: $dirs->get('config'),
                        jsonSchemaPath: $jsonSchemaPath . 'json-schema.json',
                    );
                },
            ),
        ];
    }

    public function init(DirectoriesInterface $dirs, EnvironmentInterface $env): void
    {
        $this->loadEnvVariables(dirs: $dirs, env: $env);
    }

    public function boot(ConsoleBootloader $console): void
    {
        $console->addCommand(
            VersionCommand::class,
            SchemaCommand::class,
            SelfUpdateCommand::class,
            GenerateCommand::class,
        );
    }

    public function loadEnvVariables(DirectoriesInterface $dirs, EnvironmentInterface $env): void
    {
        if ($this->init) {
            return;
        }

        $this->init = true;

        $dotenvPath = $env->get('DOTENV_PATH', $dirs->get('root') . '.env');

        if (!\file_exists(filename: $dotenvPath)) {
            return;
        }

        $path = \dirname(path: (string) $dotenvPath);
        $file = \basename(path: (string) $dotenvPath);

        foreach (Dotenv::createImmutable(paths: $path, names: $file)->load() as $key => $value) {
            $env->set($key, $value);
        }
    }
}
