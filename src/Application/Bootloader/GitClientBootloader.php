<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Lib\Git\GitClient;
use Butschster\ContextGenerator\Lib\Git\GitClientInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class GitClientBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            GitClientInterface::class => GitClient::class,
        ];
    }
}
