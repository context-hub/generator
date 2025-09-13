<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\ExceptionHandler;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Application\Logger\NullLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\BinderInterface;
use Spiral\Core\Config\Proxy;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Exceptions\Renderer\PlainRenderer;
use Spiral\Exceptions\Verbosity;
use Spiral\Files\Files;
use Spiral\Snapshots\FileSnapshot;

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
            FileSnapshot::class => static fn(
                EnvironmentInterface $env,
                DirectoriesInterface $dirs,
            ): FileSnapshot => new FileSnapshot(
                directory: $dirs->get('runtime') . '/snapshots/',
                maxFiles: (int) $env->get('SNAPSHOT_MAX_FILES', 10),
                verbosity: Verbosity::VERBOSE,
                renderer: new PlainRenderer(),
                files: new Files(),
            ),
            ExceptionReporterInterface::class => ExceptionHandler::class,
            HasPrefixLoggerInterface::class => new Proxy(
                interface: HasPrefixLoggerInterface::class,
                fallbackFactory: static fn(): HasPrefixLoggerInterface => new NullLogger(),
            ),
        ];
    }

    public function boot(BinderInterface $binder, ExceptionHandler $handler, FileSnapshot $snapshot): void
    {
        // Register injectable class
        $binder->bindInjector(LoggerInterface::class, self::class);
        $handler->addReporter(
            new readonly class($snapshot) implements ExceptionReporterInterface {
                public function __construct(
                    private FileSnapshot $fileSnapshot,
                ) {}

                public function report(\Throwable $exception): void
                {
                    $this->fileSnapshot->create($exception);
                }
            },
        );
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
