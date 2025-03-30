<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Console\DisplayCommand;
use Butschster\ContextGenerator\Console\GenerateCommand;
use Butschster\ContextGenerator\Console\InitCommand;
use Butschster\ContextGenerator\Console\SchemaCommand;
use Butschster\ContextGenerator\Console\SelfUpdateCommand;
use Butschster\ContextGenerator\Console\VersionCommand;
use Butschster\ContextGenerator\Directories;
use Dotenv\Dotenv;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\EnvironmentInterface;
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
            Directories::class => static fn(DirectoriesInterface $dirs) => new Directories(
                rootPath: $dirs->get('root'),
                outputPath: $dirs->get('output'),
                configPath: $dirs->get('config'),
                jsonSchemaPath: $dirs->get('json-schema'),
            ),
        ];
    }

    public function init(
        DirectoriesInterface $dirs,
        EnvironmentInterface $env,
    ): void {
        $this->loadEnvVariables($dirs, $env);
    }

    public function boot(
        ConsoleBootloader $console,
    ): void {
        $console->addCommand(
            VersionCommand::class,
            InitCommand::class,
            SchemaCommand::class,
            SelfUpdateCommand::class,
            GenerateCommand::class,
            DisplayCommand::class,
        );
    }

    public function loadEnvVariables(DirectoriesInterface $dirs, EnvironmentInterface $env): void
    {
        if ($this->init) {
            return;
        }

        $this->init = true;

        $dotenvPath = $env->get('DOTENV_PATH', $dirs->get('root') . '.env');

        if (!\file_exists($dotenvPath)) {
            return;
        }

        $path = \dirname((string) $dotenvPath);
        $file = \basename((string) $dotenvPath);

        foreach (Dotenv::createImmutable($path, $file)->load() as $key => $value) {
            $env->set($key, $value);
        }
    }
}
