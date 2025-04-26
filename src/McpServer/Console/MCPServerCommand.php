<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Application\Logger\FileLogger;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface as ProjectService;
use Butschster\ContextGenerator\McpServer\ProjectService\ProjectServiceFactory;
use Butschster\ContextGenerator\McpServer\ProjectService\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\ServerRunnerInterface;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutor;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Monolog\Level;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'server',
    description: 'Start MCP server',
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

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
        Application $app,
        ProjectService $projects,
    ): int {
        $currentProject = $projects->getCurrentProject();
        if ($this->configPath === null && $currentProject) {
            $this->configPath = $currentProject->hasConfigFile()
                ? $currentProject->getConfigFile()
                : $currentProject->path;

            if ($this->envFileName === null) {
                $this->envFileName = $currentProject->hasEnvFile()
                    ? $currentProject->getEnvFile()
                    : null;
            }
        }

        // Determine the effective root path based on config file path
        $dirs = $dirs
            ->determineRootPath($this->configPath)
            ->withEnvFile($this->envFileName);

        $logger = new FileLogger(
            name: 'mcp',
            filePath: (string) $dirs->getRootPath()->join('mcp.log'),
            level: match (true) {
                $this->output->isVeryVerbose() => Level::Debug,
                $this->output->isVerbose() => Level::Info,
                $this->output->isQuiet() => Level::Error,
                default => Level::Warning,
            },
        );

        $binder = $container->getBinder('root');
        $binder->bind(
            HasPrefixLoggerInterface::class,
            $logger,
        );
        $binder->bind(
            DirectoriesInterface::class,
            $dirs,
        );

        $logger->info('Starting MCP server...');

        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $dirs,
                ],
            ),
            scope: static function (
                Container $container,
                ConfigurationProvider $configProvider,
                ProjectServiceFactory $projectServiceFactory,
            ) use ($logger, $dirs, $app) {
                $rootPathStr = (string) $dirs->getRootPath();
                $logger->info(\sprintf('Using root path: %s', $rootPathStr));

                try {
                    // Get the appropriate loader based on options provided
                    if (!\is_dir($rootPathStr)) {
                        $logger->info(
                            'Loading configuration from provided path...',
                            [
                                'path' => $rootPathStr,
                            ],
                        );
                        $loader = $configProvider->fromPath((string) $dirs->getConfigPath());
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
                            DirectoriesInterface::class => $dirs,
                            ProjectServiceInterface::class => $projectServiceFactory->create(),
                            HasPrefixLoggerInterface::class => $logger,
                            ConfigLoaderInterface::class => $loader,
                            CommandExecutorInterface::class => $container->make(CommandExecutor::class, [
                                'projectRoot' => (string) $dirs->getRootPath(),
                            ]),
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
