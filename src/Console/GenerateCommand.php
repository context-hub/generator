<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\DocumentCompilerFactory;
use Spiral\Core\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'generate',
    description: 'Generate context files from configuration',
    aliases: ['build', 'compile'],
)]
final class GenerateCommand extends BaseCommand
{
    public function __construct(
        Container $container,
        private readonly Directories $dirs,
    ) {
        parent::__construct($container);
    }

    public function __invoke(
        DocumentCompilerFactory $documentCompilerFactory,
        ConfigurationProviderFactory $configurationProviderFactory,
    ): int {
        // Get the configuration options
        $inlineConfig = $this->input->getOption('inline');
        $configPath = $this->input->getOption('config-file');
        $githubToken = $this->input->getOption('github-token');
        $envFileName = $this->input->getOption('env') ?? null;

        // Determine the effective root path based on config file path
        $dirs = $this->dirs
            ->determineRootPath($configPath, $inlineConfig)
            ->withEnvFile($envFileName);

        // Create the document compiler
        $compiler = $documentCompilerFactory->create(
            dirs: $dirs,
            githubToken: $githubToken,
        );

        // Create configuration provider
        $configProvider = $configurationProviderFactory->create($dirs->withConfigPath($configPath));

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

        foreach ($loader->load()->getItems() as $document) {
            $this->output->info(\sprintf('Compiling %s...', $document->description));

            $compiledDocument = $compiler->compile($document);
            if (!$compiledDocument->errors->hasErrors()) {
                $this->output->success(\sprintf('Document compiled into %s', $document->outputPath));
                continue;
            }

            $this->output->warning(\sprintf('Document compiled into %s with errors', $document->outputPath));
            $this->output->listing(\iterator_to_array($compiledDocument->errors));
        }

        return Command::SUCCESS;
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
}
