<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Butschster\ContextGenerator\Console\Renderer\DocumentRenderer;
use Spiral\Core\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'idea-integration',
    description: 'Handle IDEA-specific tasks for running ctx and managing context.yaml files',
)]
final class IdeaIntegrationCommand extends BaseCommand
{
    public function __construct(
        Container $container,
        private readonly Directories $dirs,
    ) {
        parent::__construct($container);
    }

    public function __invoke(
        ConfigurationProviderFactory $configurationProviderFactory,
        DocumentRenderer $renderer,
    ): int {
        // Get the configuration options
        $inlineConfig = $this->input->getOption('inline');
        $configPath = $this->input->getOption('config-file');

        // Create configuration provider
        $configProvider = $configurationProviderFactory->create($this->dirs);

        try {
            // Get the appropriate loader based on options provided
            if ($inlineConfig !== null) {
                $this->output->info('Using inline JSON configuration...');
                $loader = $configProvider->fromString($inlineConfig);
            } elseif ($configPath !== null) {
                $this->output->info(\sprintf('Loading configuration from %s...', $configPath));
                $loader = $configProvider->fromPath($configPath);
            } else {
                $this->output->info('Loading configuration from default location...');
                $loader = $configProvider->fromDefaultLocation();
            }
        } catch (ConfigLoaderException $e) {
            $this->logger->error('Failed to load configuration', [
                'error' => $e->getMessage(),
            ]);

            $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        try {
            // Load the document registry
            $registry = $loader->load();

            $title = "Context: {$this->dirs->rootPath}";

            $this->output->writeln("\n" . Style::header($title));
            $this->output->writeln(Style::separator('=', \strlen($title)) . "\n");
            $documents = $registry->getItems();
            $this->output->writeln(
                Style::property("Total documents") . ": " . Style::count(\count($documents)) . "\n\n",
            );

            foreach ($documents as $index => $document) {
                /** @psalm-suppress InvalidOperand */
                $this->output->writeln(
                    Style::header("Document") . " " . Style::itemNumber($index + 1, \count($documents)) . ":",
                );
                $this->output->writeln(Style::separator('=', \strlen($title)) . "\n");
                $this->output->writeln(Style::indent($renderer->renderDocument($document)));
                $this->output->writeln("");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->output->error(\sprintf("Error displaying configuration: %s", $e->getMessage()));

            return Command::FAILURE;
        }
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'github-token',
                't',
                InputOption::VALUE_OPTIONAL,
                'GitHub token for authentication',
                \getenv('GITHUB_TOKEN') ?: null,
            )
            ->addOption(
                'inline',
                'i',
                InputOption::VALUE_REQUIRED,
                'Inline JSON configuration string. If provided, file-based configuration will be ignored',
            )
            ->addOption(
                'config-file',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to configuration file (absolute or relative to current directory).',
            )
            ->setHelp('This command handles IDEA-specific tasks for running ctx and managing context.yaml files');
    }
}
