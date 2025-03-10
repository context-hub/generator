<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Cli;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'version')]
final class GetVersion extends Command
{
    public function __construct(private readonly string $version)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Current version: ' . $this->version);

        return Command::SUCCESS;
    }
}
