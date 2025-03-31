<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\PhpDocs;

use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Spiral\Boot\Bootloader\Bootloader;

final class PhpDocsModifierBootloader extends Bootloader
{
    public function boot(SourceModifierRegistry $registry): void
    {
        $registry->register(new AstDocTransformer());
    }
}
