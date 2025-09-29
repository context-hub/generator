<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ComposerClient;

use Spiral\Boot\Bootloader\Bootloader;

final class ComposerClientBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ComposerClientInterface::class => FileSystemComposerClient::class,
        ];
    }
}
