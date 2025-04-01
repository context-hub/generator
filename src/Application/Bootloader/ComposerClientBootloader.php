<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Lib\ComposerClient\ComposerClientInterface;
use Butschster\ContextGenerator\Lib\ComposerClient\FileSystemComposerClient;
use Spiral\Boot\Bootloader\Bootloader;

final class ComposerClientBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            ComposerClientInterface::class => FileSystemComposerClient::class,
        ];
    }
}
