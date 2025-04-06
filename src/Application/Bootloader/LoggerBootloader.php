<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Application\Logger\NullLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\BinderInterface;
use Spiral\Core\Config\Proxy;
use Spiral\Core\Container\InjectorInterface;

/**
 * @implements InjectorInterface<LoggerInterface>
 */
final class LoggerBootloader extends Bootloader implements InjectorInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            HasPrefixLoggerInterface::class => new Proxy(
                interface: HasPrefixLoggerInterface::class,
                fallbackFactory: static fn(): HasPrefixLoggerInterface => new NullLogger(),
            ),
        ];
    }

    public function boot(BinderInterface $binder): void
    {
        // Register injectable class
        $binder->bindInjector(LoggerInterface::class, self::class);
    }

    public function createInjection(\ReflectionClass $class, mixed $context = null): LoggerInterface
    {
        $logger = $this->container->get(HasPrefixLoggerInterface::class);

        $prefix = null;
        if ($context instanceof \ReflectionParameter) {
            $prefix = $this->findAttribute($context)?->prefix ?? $context->getDeclaringClass()->getShortName();
        }

        if (!$prefix) {
            return $logger;
        }

        return $logger->withPrefix($prefix);
    }

    private function findAttribute(\ReflectionParameter $parameter): ?LoggerPrefix
    {
        foreach ($parameter->getAttributes(LoggerPrefix::class) as $attribute) {
            return $attribute->newInstance();
        }

        foreach ($parameter->getDeclaringClass()->getAttributes(LoggerPrefix::class) as $attribute) {
            return $attribute->newInstance();
        }

        return null;
    }
}
