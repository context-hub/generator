<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\PhpContentFilter;

use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Spiral\Boot\Bootloader\Bootloader;

final class PhpContentFilterBootloader extends Bootloader
{
    public function boot(SourceModifierRegistry $registry): void
    {
        $registry->register(new PhpContentFilter());
    }
}
