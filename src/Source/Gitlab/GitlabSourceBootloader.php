<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab;

use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Source\Gitlab\Config\GitlabServerParserPlugin;
use Butschster\ContextGenerator\Source\Gitlab\Config\ServerRegistry;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class GitlabSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GitlabSourceFetcher::class => GitlabSourceFetcher::class,
            ServerRegistry::class => ServerRegistry::class,
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        GitlabSourceFactory $factory,
    ): void {
        // Register the GitLab source fetcher with the fetcher registry
        $registry->register(GitlabSourceFetcher::class);

        // Register the GitLab source factory with the source registry
        $sourceRegistry->register($factory);
    }

    public function boot(
        ConfigLoaderBootloader $parserRegistry,
        GitlabServerParserPlugin $plugin,
    ): void {
        // Register the GitLab server parser plugin with the config loader
        $parserRegistry->registerParserPlugin($plugin);
    }
}
