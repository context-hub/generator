<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
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
}
