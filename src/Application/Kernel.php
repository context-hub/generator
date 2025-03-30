<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConfigurationBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ContentRendererBootloader;
use Butschster\ContextGenerator\Application\Bootloader\CoreBootloader;
use Butschster\ContextGenerator\Application\Bootloader\GithubClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\HttpClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\LoggerBootloader;
use Butschster\ContextGenerator\Application\Bootloader\McpServerBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ModifierBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Composer\ComposerSourceBootloader;
use Butschster\ContextGenerator\Source\File\FileSourceBootloader;
use Butschster\ContextGenerator\Source\GitDiff\GitDiffSourceBootloader;
use Butschster\ContextGenerator\Source\Github\GithubSourceBootloader;
use Butschster\ContextGenerator\Source\Text\TextSourceBootloader;
use Butschster\ContextGenerator\Source\Tree\TreeSourceBootloader;
use Butschster\ContextGenerator\Source\Url\UrlSourceBootloader;
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
            GithubClientBootloader::class,
            ConfigLoaderBootloader::class,
            ModifierBootloader::class,
            ContentRendererBootloader::class,
            McpServerBootloader::class,
            SourceFetcherBootloader::class,

            // Sources
            TextSourceBootloader::class,
            FileSourceBootloader::class,
            ComposerSourceBootloader::class,
            UrlSourceBootloader::class,
            GithubSourceBootloader::class,
            GitDiffSourceBootloader::class,
            TreeSourceBootloader::class,
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
