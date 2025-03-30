<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\McpServer\ServerFactoryInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'server',
    description: 'Start the context generator MCP server',
)]
final class MCPServerCommand extends BaseCommand
{
    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file (absolute or relative to current directory).',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'env',
        shortcut: 'e',
        description: 'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
    )]
    protected ?string $envFileName = null;

    public function __invoke(Container $container, Directories $dirs, Application $app): int
    {
        $this->setLogger(new Logger('mcp', [
            new RotatingFileHandler(
                filename: $dirs->rootPath . '/mcp.log',
                level: match (true) {
                    $this->output->isVeryVerbose() => Level::Debug,
                    $this->output->isVerbose() => Level::Info,
                    $this->output->isQuiet() => Level::Error,
                    default => Level::Warning,
                },
            ),
        ]));

        $this->logger->info('Starting MCP server...');

        // Determine the effective root path based on config file path
        $dirs = $dirs
            ->determineRootPath($this->configPath)
            ->withEnvFile($this->envFileName);

        $container->bindSingleton(Directories::class, $dirs);

        $configProvider = $container->get(ConfigurationProvider::class);
        $this->logger->info(\sprintf('Using root path: %s', $dirs->rootPath));

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

        $binder = $container->getBinder('root');

        $binder->bindSingleton(ConfigLoaderInterface::class, $loader);

        $factory = $container->get(ServerFactoryInterface::class);
        // Create and run the MCP server
        $factory->create()->run(name: \sprintf('%s %s', $app->name, $app->version));

        return self::SUCCESS;
    }
}
