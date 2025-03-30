<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerFactory;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Spiral\Console\Command;
use Spiral\Core\BinderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @protected HasPrefixLoggerInterface $logger
 */
abstract class BaseCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;

        $this->container
            ->get(BinderInterface::class)
            ->bindSingleton(LoggerInterface::class, $logger);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \assert($output instanceof SymfonyStyle);

        $this->input = $input;
        $this->output = $output;

        $this->container
            ->get(BinderInterface::class)
            ->bindSingleton(
                HasPrefixLoggerInterface::class,
                $logger = LoggerFactory::create(
                    output: $output,
                    loggingEnabled: $output->isVerbose() || $output->isDebug() || $output->isVeryVerbose(),
                ),
            );
        $this->setLogger($logger);

        \assert($this->logger instanceof HasPrefixLoggerInterface);
        \assert($this->logger instanceof LoggerInterface);

        return parent::execute($input, new SymfonyStyle($input, $output));
    }
}
