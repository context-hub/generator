<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConfigurationBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ContentRendererBootloader;
use Butschster\ContextGenerator\Application\Bootloader\CoreBootloader;
use Butschster\ContextGenerator\Application\Bootloader\GitClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\HttpClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\LoggerBootloader;
use Butschster\ContextGenerator\Application\Bootloader\McpServerBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ModifierBootloader;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Exception\BootException;

class Kernel extends AbstractKernel
{
    #[\Override]
    protected function defineSystemBootloaders(): array
    {
        return [
            ConfigurationBootloader::class,
            LoggerBootloader::class,
            ConsoleBootloader::class,
        ];
    }

    #[\Override]
    protected function defineBootloaders(): array
    {
        return [
            CoreBootloader::class,
            HttpClientBootloader::class,
            GitClientBootloader::class,
            ConfigLoaderBootloader::class,
            ModifierBootloader::class,
            ContentRendererBootloader::class,
            McpServerBootloader::class,
        ];
    }

    /**
     * Each application can define it's own boot sequence.
     */
    protected function bootstrap(): void {}

    /**
     * Normalizes directory list and adds all required aliases.
     */
    protected function mapDirectories(array $directories): array
    {
        if (!isset($directories['root'])) {
            throw new BootException('Missing required directory `root`');
        }

        return \array_merge(
            [
                // custom directories
            ],
            $directories,
        );
    }
}
