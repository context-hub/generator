<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\NullLogger;
use Psr\Log\LoggerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Config\Proxy;

final class LoggerBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            LoggerInterface::class => new Proxy(
                interface: LoggerInterface::class,
                fallbackFactory: static fn(): LoggerInterface => new NullLogger(),
            ),
            HasPrefixLoggerInterface::class => new Proxy(
                interface: HasPrefixLoggerInterface::class,
                fallbackFactory: static fn(): LoggerInterface => new NullLogger(),
            ),
        ];
    }
}
