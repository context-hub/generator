<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\BinderInterface;
use Spiral\Exceptions\ExceptionHandlerInterface;

final class LoggerBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            LoggerFactory::class => LoggerFactory::class,
            LoggerInterface::class => NullLogger::class,
            HasPrefixLoggerInterface::class => LoggerInterface::class,
        ];
    }

    public function init(
        LoggerFactory $factory,
        BinderInterface $binder,
        ExceptionHandlerInterface $handler,
    ): void {
        $binder->bindSingleton(
            LoggerInterface::class,
            $logger = $factory->create(),
        );

        if ($handler instanceof LoggerAwareInterface) {
            $handler->setLogger($logger);
        }
    }
}
