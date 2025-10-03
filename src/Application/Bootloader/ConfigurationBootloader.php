<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Config\ConfigManager;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Loader\DirectoryLoader;
use Spiral\Config\Loader\FileLoaderInterface;
use Spiral\Config\Loader\JsonLoader;
use Spiral\Config\Loader\PhpLoader;
use Spiral\Core\BinderInterface;
use Spiral\Core\ConfigsInterface;

final class ConfigurationBootloader extends Bootloader
{
    private readonly ConfiguratorInterface $configurator;

    /** @var FileLoaderInterface[] */
    private array $loaders;

    public function __construct(
        ContainerInterface $container,
        private readonly DirectoriesInterface $directories,
        private readonly BinderInterface $binder,
    ) {
        $this->loaders = [
            'php' => $container->get(PhpLoader::class),
            'json' => $container->get(JsonLoader::class),
        ];

        $this->configurator = $this->createConfigManager();
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // configuration
            ConfigsInterface::class => ConfiguratorInterface::class,
            ConfiguratorInterface::class => ConfigManager::class,
            ConfigManager::class => $this->configManager(...),
        ];
    }

    public function addLoader(string $ext, FileLoaderInterface $loader): void
    {
        if (!isset($this->loaders[$ext]) || $loader::class !== $this->loaders[$ext]::class) {
            $this->loaders[$ext] = $loader;
            $this->binder->bindSingleton(ConfigManager::class, $this->createConfigManager());
        }
    }

    private function createConfigManager(): ConfigManager
    {
        return new ConfigManager(
            loader: new DirectoryLoader(directory: $this->directories->get('config'), loaders: $this->loaders),
        );
    }

    private function configManager(): ConfiguratorInterface
    {
        return $this->configurator;
    }
}
