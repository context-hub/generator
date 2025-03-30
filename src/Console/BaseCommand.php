<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Spiral\Core\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @protected HasPrefixLoggerInterface $logger
 */
abstract class BaseCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected InputInterface $input;

    /** @var SymfonyStyle */
    protected OutputInterface $output;

    public function __construct(
        protected Container $container,
    ) {
        parent::__construct();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;

        $this->container->bindSingleton(LoggerInterface::class, $logger);
    }

    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \assert($output instanceof SymfonyStyle);

        $this->input = $input;
        $this->output = $output;

        $this->container->bindSingleton(OutputInterface::class, $output);

        $this->container->bindSingleton(
            HasPrefixLoggerInterface::class,
            $logger = LoggerFactory::create(
                output: $output,
                loggingEnabled: $output->isVerbose() || $output->isDebug() || $output->isVeryVerbose(),
            ),
        );
        $this->setLogger($logger);

        \assert($this->logger instanceof HasPrefixLoggerInterface);
        \assert($this->logger instanceof LoggerInterface);

        if (!\method_exists($this, '__invoke')) {
            throw new \RuntimeException('The __invoke method is not defined in the command class.');
        }

        /** @psalm-suppress InvalidArgument */
        return $this->container->invoke([$this, '__invoke']);
    }
}
