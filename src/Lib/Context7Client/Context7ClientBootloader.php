<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Context7Client;

use Butschster\ContextGenerator\Application\Bootloader\LoggerBootloader;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientBootloader;
use Spiral\Boot\Bootloader\Bootloader;

final class Context7ClientBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [
            HttpClientBootloader::class,
            LoggerBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            Context7ClientInterface::class => Context7Client::class,
        ];
    }
}
