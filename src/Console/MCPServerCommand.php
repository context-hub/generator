<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\DocumentCompilerFactory;
use Butschster\ContextGenerator\McpServer\ServerFactory;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Spiral\Core\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'server',
    description: 'Start the context generator MCP server',
)]
final class MCPServerCommand extends BaseCommand
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
        $this->setLogger(new Logger('mcp', [
            new RotatingFileHandler(
                filename: $this->dirs->rootPath . '/mcp.log',
                level: match (true) {
                    $this->output->isVeryVerbose() => Level::Debug,
                    $this->output->isVerbose() => Level::Info,
                    $this->output->isQuiet() => Level::Error,
                    default => Level::Warning,
                },
            ),
        ]));

        $this->logger->info('Starting MCP server...');

        $envFileName = $this->input->getOption('env') ?? null;
        $configPath = $this->input->getOption('config-file');

        // Determine the effective root path based on config file path
        $dirs = $this->dirs
            ->determineRootPath($configPath)
            ->withEnvFile($envFileName);

        $this->logger->info(\sprintf('Using root path: %s', $dirs->rootPath));

        $this->container->bindSingleton(
            Directories::class,
            $dirs = $this->dirs
                ->withConfigPath($configPath),
        );

        // Create the document compiler
        $compiler = $documentCompilerFactory->create(dirs: $dirs);

        // Create configuration provider
        $configProvider = $configurationProviderFactory->create($dirs);

        try {
            // Get the appropriate loader based on options provided
            if (!\is_dir($dirs->rootPath)) {
                $this->logger->info(
                    'Loading configuration from provided path...',
                    [
                        'path' => $dirs->rootPath,
                    ],
                );
                $loader = $configProvider->fromPath($dirs->configPath);
            } else {
                $this->logger->info('Using default configuration location...');
                $loader = $configProvider->fromDefaultLocation();
            }
        } catch (ConfigLoaderException $e) {
            $this->logger->error('Failed to load configuration', [
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        // Register dependencies
        $this->container->bindSingleton(ConfigLoaderInterface::class, $loader);
        $this->container->bindSingleton(DocumentCompiler::class, $compiler);


        // Create and run the MCP server
        $server = $this->container->get(ServerFactory::class)->create($this->logger);

        $server->run(name: $this->input->getOption('name'));

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_REQUIRED,
                'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                'Server name',
                'Context Generator',
            )
            ->addOption(
                'config-file',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to configuration file (absolute or relative to current directory).',
            );
    }
}
