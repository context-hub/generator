<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier;

use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Modifier\Alias\ModifierAliasesParserPlugin;
use Spiral\Boot\Bootloader\Bootloader;

final class ModifierBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            SourceModifierRegistry::class => SourceModifierRegistry::class,
        ];
    }

    public function boot(
        ConfigLoaderBootloader $parserRegistry,
        ModifierAliasesParserPlugin $plugin,
    ): void {
        $parserRegistry->registerParserPlugin($plugin);
    }
}
