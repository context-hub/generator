<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerFactory;
use Butschster\ContextGenerator\Console\Output\OutputServiceFactory;
use Butschster\ContextGenerator\Console\Output\OutputServiceInterface;
use Psr\Log\LoggerInterface;
use Spiral\Console\Command;
use Spiral\Core\BinderInterface;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @protected HasPrefixLoggerInterface $logger
 */
abstract class BaseCommand extends Command
{
    protected LoggerInterface $logger;
    protected OutputServiceInterface $outputService;

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \assert($output instanceof SymfonyStyle);


        // Create the OutputService with all renderers
        $this->outputService = OutputServiceFactory::createWithRenderers($output);

        $this->input = $input;
        $this->output = $output;

        $logger = LoggerFactory::create(
            output: $output,
            loggingEnabled: $output->isVerbose() || $output->isDebug() || $output->isVeryVerbose(),
        );

        $this->logger = $logger;

        \assert($this->logger instanceof HasPrefixLoggerInterface);
        \assert($this->logger instanceof LoggerInterface);

        $this->container
            ->get(BinderInterface::class)
            ->getBinder('root')
            ->bind(HasPrefixLoggerInterface::class, $logger);

        return $this->container->get(ScopeInterface::class)->runScope(
            bindings: new Scope(
                bindings: [
                    // LoggerInterface::class => $logger,
                    HasPrefixLoggerInterface::class => $logger,
                ],
            ),
            scope: fn() => parent::execute($input, $output),
        );
    }
}
