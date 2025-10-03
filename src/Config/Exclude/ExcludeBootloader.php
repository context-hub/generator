<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class ExcludeBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ExcludeRegistryInterface::class => ExcludeRegistry::class,
        ];
    }

    public function boot(ConfigLoaderBootloader $configLoader, ExcludeParserPlugin $excludeParser): void
    {
        // Register the exclude parser plugin
        $configLoader->registerParserPlugin(plugin: $excludeParser);
    }
}
