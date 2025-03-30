<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\ConfigLoader\Parser\ParserPluginRegistry;
use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Spiral\Boot\Bootloader\Bootloader;

final class ConfigLoaderBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ParserPluginRegistry::class => static fn() => ParserPluginRegistry::createDefault(),
            ConfigurationProviderFactory::class => ConfigurationProviderFactory::class,
        ];
    }
}
