<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface as ProjectService;
use Butschster\ContextGenerator\McpServer\ServerRunnerInterface;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutor;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Psr\Log\LoggerInterface;
use Spiral\Boot\DirectoriesInterface as SpiralDirectoriesInterface;
use Spiral\Boot\EnvironmentInterface;
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
        name: 'sse',
        description: 'Enable SSE (Server-Sent Events) support',
    )]
    protected bool $isSse = false;

    #[Option(
        name: 'host',
        description: 'SSE host to bind to (default: 127.0.0.1)',
    )]
    protected string $host = '127.0.0.1';

    #[Option(
        name: 'port',
        description: 'SSE port to bind to (default: 8080)',
    )]
    protected int $port = 8080;

    #[Option(
        name: 'env',
        shortcut: 'e',
        description: 'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
    )]
    protected ?string $envFileName = null;

    #[Option(
        name: 'work-dir',
        shortcut: 'w',
        description: 'Path to working directory. If not provided, will use "./.context"',
    )]
    protected ?string $workDir = null;

    #[Option(
        name: 'state-dir',
        shortcut: 's',
        description: 'Path to directory containing .project-state.json file. If not provided, uses default global state location.',
    )]
    protected ?string $stateDir = null;

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
        SpiralDirectoriesInterface $spiralDirs,
        Application $app,
    ): int {
        $this->logger->info('Starting MCP server...');

        // Set custom state directory if provided (must be done before getting ProjectService)
        if ($this->stateDir !== null) {
            $stateDir = \realpath($this->stateDir) ?: $this->stateDir;
            $spiralDirs->set('global-state', $stateDir);
            $this->logger->info('Using custom state directory', ['stateDir' => $stateDir]);
        }

        // Now get ProjectService (will use the updated global-state directory)
        $projects = $container->get(ProjectService::class);

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
            ->withOutputPath($this->workDir)
            ->withEnvFile($this->envFileName);

        $binder = $container->getBinder('root');
        $binder->bind(
            DirectoriesInterface::class,
            $dirs,
        );

        $envs = [];
        if ($this->isSse) {
            $envs['MCP_TRANSPORT'] = 'http';
        }

        if ($this->host) {
            $envs['MCP_HOST'] = $this->host;
        }

        if ($this->port) {
            $envs['MCP_PORT'] = $this->port;
        }

        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $dirs,
                ],
            ),
            scope: static function (
                Container $container,
                ConfigurationProvider $configProvider,
                LoggerInterface $logger,
                EnvironmentInterface $env,
            ) use ($dirs, $app, $envs) {
                $rootPathStr = (string) $dirs->getRootPath();
                $logger->info(\sprintf('Using root path: %s', $rootPathStr));

                foreach ($envs as $key => $value) {
                    $env->set($key, $value);
                }

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

                // Prepare scope bindings
                $scopeBindings = [
                    DirectoriesInterface::class => $dirs,
                    HasPrefixLoggerInterface::class => $logger,
                    ConfigLoaderInterface::class => $loader,
                    CommandExecutorInterface::class => $container->make(CommandExecutor::class, [
                        'projectRoot' => (string) $dirs->getRootPath(),
                    ]),
                    EnvironmentInterface::class => $env,
                ];

                $container->runScope(
                    bindings: new Scope(
                        name: AppScope::Mcp,
                        bindings: $scopeBindings,
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
