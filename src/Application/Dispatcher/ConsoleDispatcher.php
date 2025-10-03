<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Dispatcher;

use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Console\Console;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Exceptions\ExceptionHandlerInterface;
use Spiral\Exceptions\Verbosity;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ConsoleDispatcher implements DispatcherInterface
{
    public function __construct(
        private FinalizerInterface $finalizer,
        private Container $container,
        private ExceptionHandlerInterface $errorHandler,
    ) {}

    public static function canServe(EnvironmentInterface $env): bool
    {
        return true;
    }

    public function serve(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput();
        // On demand to save some memory.
        $output ??= new SymfonyStyle(input: $input, output: new ConsoleOutput());

        return $this->container->runScope(
            bindings: new Scope(
                name: 'console',
            ),
            scope: function (Container $container) use ($input, $output) {
                $console = $container->get(id: Console::class);

                try {
                    return $console->run(
                        $input->getFirstArgument() ?? 'generate',
                        $input,
                        $output,
                    )->getCode();
                } catch (\Throwable $e) {
                    $this->handleException(exception: $e, output: $output);

                    return 255;
                } finally {
                    $this->finalizer->finalize(false);
                }
            },
        );
    }

    protected function handleException(\Throwable $exception, OutputInterface $output): void
    {
        $this->errorHandler->report($exception);
        $output->write(
            $this->errorHandler->render(
                $exception,
                verbosity: $this->mapVerbosity(output: $output),
                format: 'cli',
            ),
        );
    }

    private function mapVerbosity(OutputInterface $output): Verbosity
    {
        return match (true) {
            $output->isDebug() => Verbosity::DEBUG,
            $output->isVeryVerbose() => Verbosity::VERBOSE,
            default => Verbosity::BASIC,
        };
    }
}
