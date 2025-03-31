<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Application\Logger\FileLogger;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\McpServer\ServerRunnerInterface;
use Monolog\Level;
use Psr\Log\LoggerInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
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
        $logger = new FileLogger(
            name: 'mcp',
            filePath: $dirs->rootPath . '/mcp.log',
            level: match (true) {
                $this->output->isVeryVerbose() => Level::Debug,
                $this->output->isVerbose() => Level::Info,
                $this->output->isQuiet() => Level::Error,
                default => Level::Warning,
            },
        );

        $logger->info('Starting MCP server...');

        // Determine the effective root path based on config file path
        $dirs = $dirs
            ->determineRootPath($this->configPath)
            ->withEnvFile($this->envFileName);

        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    LoggerInterface::class => $logger,
                    HasPrefixLoggerInterface::class => $logger,
                    Directories::class => $dirs,
                ],
            ),
            scope: static function (Container $container, ConfigurationProvider $configProvider) use (
                $logger,
                $dirs,
                $app,
            ) {
                $logger->info(\sprintf('Using root path: %s', $dirs->rootPath));

                try {
                    // Get the appropriate loader based on options provided
                    if (!\is_dir($dirs->rootPath)) {
                        $logger->info(
                            'Loading configuration from provided path...',
                            [
                                'path' => $dirs->rootPath,
                            ],
                        );
                        $loader = $configProvider->fromPath($dirs->configPath);
                    } else {
                        $logger->info('Using default configuration location...');
                        $loader = $configProvider->fromDefaultLocation();
                    }
                } catch (ConfigLoaderException $e) {
                    $logger->error('Failed to load configuration', [
                        'error' => $e->getMessage(),
                    ]);

                    return Command::FAILURE;
                }

                $container->runScope(
                    bindings: new Scope(
                        name: AppScope::Mcp,
                        bindings: [
                            ConfigLoaderInterface::class => $loader,
                        ],
                    ),
                    scope: static function (ServerRunnerInterface $factory) use ($app): void {
                        $factory->run(name: \sprintf('%s %s', $app->name, $app->version));
                    },
                );

                return self::SUCCESS;
            },
        );
    }
}
