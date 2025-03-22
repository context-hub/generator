<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactory;
use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Console\Renderer\DocumentRenderer;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\Document\DocumentsParserPlugin;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Modifier\Alias\AliasesRegistry;
use Butschster\ContextGenerator\Modifier\Alias\ModifierAliasesParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'display',
    description: 'Display the context configuration in a human-readable format',
)]
final class DisplayCommand extends Command
{
    public function __construct(
        private readonly FilesInterface $files,
        private readonly string $rootPath = '.',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command displays the context configuration in a human-readable format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputStyle = new SymfonyStyle($input, $output);

        try {
            // Create a config loader factory
            $loader = (new ConfigLoaderFactory(
                files: $this->files,
                rootPath: $this->rootPath,
            ))->create(
                rootPath: $this->rootPath,
                parserPlugins: $this->getParserPlugins(),
            );
        } catch (ConfigLoaderException $e) {
            $outputStyle->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        try {
            // Load the document registry
            $registry = $loader->load();

            // Create renderer and render each document
            $renderer = new DocumentRenderer();

            $title = "Context: {$this->rootPath}";

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
            $outputStyle->error("Error displaying configuration: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Get parser plugins for the config loader
     *
     * @return array<ConfigParserPluginInterface>
     */
    private function getParserPlugins(): array
    {
        $modifierResolver = new ModifierResolver(
            aliasesRegistry: $aliasesRegistry = new AliasesRegistry(),
        );

        return [
            new ModifierAliasesParserPlugin(
                aliasesRegistry: $aliasesRegistry,
            ),
            new DocumentsParserPlugin(
                modifierResolver: $modifierResolver,
            ),
        ];
    }
}
