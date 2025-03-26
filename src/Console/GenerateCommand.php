<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigurationProviderConfig;
use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Butschster\ContextGenerator\DocumentCompilerFactory;
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
    name: 'generate',
    description: 'Generate context files from configuration',
    aliases: ['build', 'compile'],
)]
final class GenerateCommand extends Command
{
    use DetermineRootPath;

    private LoggerInterface $logger;

    public function __construct(
        private readonly string $rootPath,
        private readonly string $outputPath,
        private readonly FilesInterface $files,
        private readonly DocumentCompilerFactory $documentCompilerFactory,
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
                'env',
                'e',
                InputOption::VALUE_REQUIRED,
                'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
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
            );
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
        $githubToken = $input->getOption('github-token');
        $envFileName = $input->getOption('env') ?? null;

        // Determine the effective root path based on config file path
        $effectiveRootPath = $this->determineRootPath($configPath, $inlineConfig);

        // Determine the env file path
        $envFilePath = $envFileName ? $effectiveRootPath : null;

        // Create the document compiler
        $compiler = $this->documentCompilerFactory->create(
            rootPath: $effectiveRootPath,
            outputPath: $this->outputPath,
            logger: $this->logger,
            githubToken: $githubToken,
            envFilePath: $envFilePath,
            envFileName: $envFileName,
        );

        // Create configuration provider
        $configProvider = $this->configurationProviderFactory->create(
            new ConfigurationProviderConfig(
                rootPath: $effectiveRootPath,
                files: $this->files,
                logger: $this->logger,
                configPath: $configPath,
                inlineConfig: $inlineConfig,
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

        foreach ($loader->load()->getItems() as $document) {
            $output->info(\sprintf('Compiling %s...', $document->description));

            $compiledDocument = $compiler->compile($document);
            if (!$compiledDocument->errors->hasErrors()) {
                $output->success(\sprintf('Document compiled into %s', $document->outputPath));
                continue;
            }

            $output->warning(\sprintf('Document compiled into %s with errors', $document->outputPath));
            $output->listing(\iterator_to_array($compiledDocument->errors));
        }

        return Command::SUCCESS;
    }
}
