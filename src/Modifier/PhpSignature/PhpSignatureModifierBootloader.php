<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\PhpSignature;

use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Spiral\Boot\Bootloader\Bootloader;

final class PhpSignatureModifierBootloader extends Bootloader
{
    public function boot(SourceModifierRegistry $registry): void
    {
        $registry->register(new PhpSignature());
    }
}
