<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'init')]
final class InitCommand extends Command
{
    public function __construct(public string $baseDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'filename',
                mode: InputArgument::OPTIONAL,
                description: 'The name of the file to create',
                default: 'context.json',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getArgument('filename');

        $filePath = \sprintf('%s/%s', $this->baseDir, $filename);

        if (\file_exists($filePath)) {
            $output->writeln(\sprintf('<error>Config %s already exists</error>', $filePath));

            return Command::FAILURE;
        }

        $content = \json_encode(new DocumentRegistry([
            new Document(
                description: 'Your description here',
                outputPath: 'context.md',
                firstSource: new TextSource(
                    content: 'My first context',
                    description: 'First context',
                ),
            ),
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        \file_put_contents($filePath, $content);

        return Command::SUCCESS;
    }
}
