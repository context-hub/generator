<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Sanitizer;

use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Spiral\Boot\Bootloader\Bootloader;

final class SanitizerModifierBootloader extends Bootloader
{
    public function boot(SourceModifierRegistry $registry): void
    {
        $registry->register(new SanitizerModifier());
    }
}
