<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Registry;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class SourceRegistryBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            SourceRegistryInterface::class => SourceRegistry::class,
            SourceProviderInterface::class => SourceRegistry::class,
            SourceRegistry::class => SourceRegistry::class,
        ];
    }
}
