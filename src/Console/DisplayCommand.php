<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigurationProviderConfig;
use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Butschster\ContextGenerator\Console\Renderer\DocumentRenderer;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'display',
    description: 'Display the context configuration in a human-readable format',
)]
final class DisplayCommand extends Command
{
    use DetermineRootPath;

    private LoggerInterface $logger;

    public function __construct(
        private readonly string $rootPath,
        private readonly FilesInterface $files,
        private readonly ConfigurationProviderFactory $configurationProviderFactory,
    ) {
        parent::__construct();
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
            ->setHelp('This command displays the context configuration in a human-readable format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \assert($output instanceof SymfonyStyle);

        $this->logger = LoggerFactory::create(
            output: $output,
            loggingEnabled: $output->isVerbose() || $output->isDebug() || $output->isVeryVerbose(),
        );

        \assert($this->logger instanceof HasPrefixLoggerInterface);
        \assert($this->logger instanceof LoggerInterface);

        // Get the configuration options
        $inlineConfig = $input->getOption('inline');
        $configPath = $input->getOption('config-file');

        // Create configuration provider
        $configProvider = $this->configurationProviderFactory->create(
            new ConfigurationProviderConfig(
                rootPath: $this->rootPath,
                files: $this->files,
                configPath: $configPath,
                inlineConfig: $inlineConfig,
                logger: $this->logger,
            ),
        );

        try {
            // Get the appropriate loader based on options provided
            if ($inlineConfig !== null) {
                $output->info('Using inline JSON configuration...');
                $loader = $configProvider->fromString($inlineConfig);
            } elseif ($configPath !== null) {
                $output->info(\sprintf('Loading configuration from %s...', $configPath));
                $loader = $configProvider->fromPath($configPath);
            } else {
                $output->info('Loading configuration from default location...');
                $loader = $configProvider->fromDefaultLocation();
            }
        } catch (ConfigLoaderException $e) {
            $this->logger->error('Failed to load configuration', [
                'error' => $e->getMessage(),
            ]);

            $output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

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
            $output->error("Error displaying configuration: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
