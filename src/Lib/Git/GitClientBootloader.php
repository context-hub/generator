<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

use Spiral\Boot\Bootloader\Bootloader;

final class GitClientBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            CommandsExecutorInterface::class => CommandsExecutor::class,
        ];
    }
}
