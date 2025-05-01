<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Config\Exclude\ExcludeParserPlugin;
use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistry;
use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
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
        $configLoader->registerParserPlugin($excludeParser);
    }
}
