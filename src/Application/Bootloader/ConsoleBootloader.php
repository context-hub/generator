<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Dispatcher\ConsoleDispatcher;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Console\CommandCore;
use Spiral\Console\CommandCoreFactory;
use Spiral\Console\Config\ConsoleConfig;
use Spiral\Console\Console;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\BinderInterface;

#[Singleton]
final class ConsoleBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function init(AbstractKernel $kernel, BinderInterface $binder): void
    {
        $kernel->bootstrapped(static function (AbstractKernel $kernel): void {
            $kernel->addDispatcher(ConsoleDispatcher::class);
        });

        // Registering necessary scope bindings
        $commandBinder = $binder->getBinder('console.command');
        $commandBinder->bindSingleton(CommandCoreFactory::class, CommandCoreFactory::class);
        $commandBinder->bindSingleton(CommandCore::class, CommandCore::class);

        $binder->getBinder('console')->bindSingleton(Console::class, Console::class);

        $this->config->setDefaults(
            ConsoleConfig::CONFIG,
            [
                'interceptors' => [],
                'commands' => [],
            ],
        );
    }

    public function addInterceptor(string ...$interceptors): void
    {
        foreach ($interceptors as $interceptor) {
            $this->config->modify(
                ConsoleConfig::CONFIG,
                new Append('interceptors', null, $interceptor),
            );
        }
    }

    /**
     * @param class-string<\Symfony\Component\Console\Command\Command> ... $commands
     */
    public function addCommand(string ...$commands): void
    {
        foreach ($commands as $command) {
            $this->config->modify(
                ConsoleConfig::CONFIG,
                new Append('commands', null, $command),
            );
        }
    }
}
