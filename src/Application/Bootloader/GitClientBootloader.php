<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Lib\Git\CommandsExecutor;
use Butschster\ContextGenerator\Lib\Git\CommandsExecutorInterface;
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
