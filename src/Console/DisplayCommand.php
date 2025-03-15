<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Console\Renderer\DocumentRenderer;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Loader\ConfigRegistry\ConfigParser;
use Butschster\ContextGenerator\Loader\ConfigRegistry\DocumentsParserPlugin;
use Butschster\ContextGenerator\Loader\JsonConfigDocumentsLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'display',
    description: 'Display the context configuration in a human-readable format',
)]
final class DisplayCommand extends Command
{
    public function __construct(
        private readonly FilesInterface $files,
        private readonly string $defaultConfigPath = 'context.json',
        private readonly string $rootPath = '.',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command displays the context configuration in a human-readable format')
            ->addArgument(
                'config',
                InputArgument::OPTIONAL,
                'Path to the configuration file',
                $this->defaultConfigPath,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getArgument('config');

        if (!$this->files->exists($configPath)) {
            $output->writeln("<error>Configuration file not found: $configPath</error>");
            return Command::FAILURE;
        }

        try {
            // Load the configuration using JsonConfigDocumentsLoader
            // todo use factory
            $loader = new JsonConfigDocumentsLoader(
                files: $this->files,
                parser: new ConfigParser(
                    rootPath: $this->rootPath,
                    documentsParser: new DocumentsParserPlugin(),
                ),
                configPath: $configPath,
                rootPath: $this->rootPath,
            );

            if (!$loader->isSupported()) {
                $output->writeln("<error>Configuration file format not supported: $configPath</error>");
                return Command::FAILURE;
            }

            // Load the document registry
            $registry = $loader->load();

            // Create renderer and render each document
            $renderer = new DocumentRenderer();

            $title = "Context: {$configPath}";

            $output->writeln("\n" . Style::header($title));
            $output->writeln(Style::separator('=', \strlen($title)) . "\n");

            $documents = $registry->getItems();
            $output->writeln(Style::property("Total documents") . ": " . Style::count(\count($documents)) . "\n\n");

            foreach ($documents as $index => $document) {
                /** @psalm-suppress InvalidOperand */
                $output->writeln(
                    Style::header("Document") . " " . Style::itemNumber($index + 1, \count($documents)) . ":",
                );
                $output->writeln(Style::separator('=', \strlen($title)) . "\n");
                $output->writeln(Style::indent($renderer->renderDocument($document)));
                $output->writeln("");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error displaying configuration: {$e->getMessage()}</error>");

            if ($output->isVerbose()) {
                $output->writeln("<error>{$e->getTraceAsString()}</error>");
            }

            return Command::FAILURE;
        }
    }
}
