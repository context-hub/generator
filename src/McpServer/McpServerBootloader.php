<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Application\Bootloader\HttpClientBootloader;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\McpServer\Action\Prompts\FilesystemOperationsAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\GetPromptAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\ListPromptsAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\ProjectStructurePromptAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\GetDocumentContentResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\JsonSchemaResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\ListResourcesAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextGetAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextRequestAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Docs\FetchLibraryDocsAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Docs\LibrarySearchAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\ExecuteCustomToolAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryListAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileApplyPatchAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileMoveAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReadAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWriteAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\GitAddAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\GitCommitAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\GitStatusAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\ListToolsAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\GetPromptToolAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\ListPromptsToolAction;
use Butschster\ContextGenerator\McpServer\Config\McpConfig;
use Butschster\ContextGenerator\McpServer\Console\MCPServerCommand;
use Butschster\ContextGenerator\McpServer\Projects\Actions\ProjectsListToolAction;
use Butschster\ContextGenerator\McpServer\Projects\Actions\ProjectSwitchToolAction;
use Butschster\ContextGenerator\McpServer\Projects\McpProjectsBootloader;
use Butschster\ContextGenerator\McpServer\ProjectService\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Prompt\McpPromptBootloader;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Prompts\PromptsHandler;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Prompts\PromptsHandlerInterface;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Resources\ResourcesHandler;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Resources\ResourcesHandlerInterface;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Tools\ToolsHandler;
use Butschster\ContextGenerator\McpServer\Routing\Handler\Tools\ToolsHandlerInterface;
use Butschster\ContextGenerator\McpServer\Routing\McpResponseStrategy;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Butschster\ContextGenerator\McpServer\Server\Runner;
use Butschster\ContextGenerator\McpServer\Server\RunnerInterface;
use Butschster\ContextGenerator\McpServer\Tool\McpToolBootloader;
use League\Route\Router;
use League\Route\Strategy\StrategyInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Config\Proxy as ConfigProxy;

final class McpServerBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    #[\Override]
    public function defineDependencies(): array
    {
        return [
            HttpClientBootloader::class,
            McpToolBootloader::class,
            McpPromptBootloader::class,
            McpProjectsBootloader::class,
        ];
    }

    public function init(EnvironmentInterface $env): void
    {
        $isCommonProject = (bool) $env->get('MCP_PROJECT_COMMON', false);

        $this->config->setDefaults(
            McpConfig::CONFIG,
            [
                'document_name_format' => $env->get('MCP_DOCUMENT_NAME_FORMAT', '[{path}] {description}'),
                'transport' => [
                    'type' => $env->get('MCP_TRANSPORT', 'stdio'),
                    'swoole' => [
                        'host' => $env->get('MCP_HTTP_HOST', '127.0.0.1'),
                        'port' => (int) $env->get('MCP_HTTP_PORT', 8080),
                        'session_store' => $env->get('MCP_HTTP_SESSION_STORE', 'file'),
                        'session_store_path' => $env->get('MCP_HTTP_SESSION_STORE_PATH'),
                        'cors_enabled' => (bool) $env->get('MCP_HTTP_CORS_ENABLED', true),
                        'cors_origins' => \array_filter(
                            \explode(',', (string) $env->get('MCP_HTTP_CORS_ORIGINS', '*')),
                        ),
                        'max_request_size' => (int) $env->get('MCP_HTTP_MAX_REQUEST_SIZE', 10485760),

                        // SSE-specific configuration
                        'sse_enabled' => (bool) $env->get('MCP_HTTP_SSE_ENABLED', true),
                        'max_sse_connections' => (int) $env->get('MCP_HTTP_MAX_SSE_CONNECTIONS', 1000),
                        'sse_heartbeat_interval' => (int) $env->get('MCP_HTTP_SSE_HEARTBEAT_INTERVAL', 30),
                        'sse_reconnect_timeout' => (int) $env->get('MCP_HTTP_SSE_RECONNECT_TIMEOUT', 5),
                        'sse_buffer_size' => (int) $env->get('MCP_HTTP_SSE_BUFFER_SIZE', 8192),

                        // Swoole server configuration
                        'worker_num' => (int) $env->get('MCP_SWOOLE_WORKER_NUM', 4),
                        'task_worker_num' => (int) $env->get('MCP_SWOOLE_TASK_WORKER_NUM', 2),
                        'max_connections' => (int) $env->get('MCP_SWOOLE_MAX_CONNECTIONS', 1024),
                        'connection_timeout' => (int) $env->get('MCP_SWOOLE_CONNECTION_TIMEOUT', 300),
                        'buffer_size' => (int) $env->get('MCP_SWOOLE_BUFFER_SIZE', 32768),
                        'package_max_length' => (int) $env->get('MCP_SWOOLE_PACKAGE_MAX_LENGTH', 2097152),

                        // Performance settings
                        'enable_coroutine' => (bool) $env->get('MCP_SWOOLE_ENABLE_COROUTINE', true),
                        'enable_static_handler' => (bool) $env->get('MCP_SWOOLE_ENABLE_STATIC_HANDLER', false),
                        'max_coroutines' => (int) $env->get('MCP_SWOOLE_MAX_COROUTINES', 1024),
                        'socket_buffer_size' => (int) $env->get('MCP_SWOOLE_SOCKET_BUFFER_SIZE', 2097152),

                        // Logging and monitoring
                        'enable_stats' => (bool) $env->get('MCP_SWOOLE_ENABLE_STATS', true),
                        'stats_interval' => (int) $env->get('MCP_SWOOLE_STATS_INTERVAL', 60),
                        'log_requests' => (bool) $env->get('MCP_SWOOLE_LOG_REQUESTS', false),
                    ],
                ],
                'file_operations' => [
                    'enable' => (bool) $env->get('MCP_FILE_OPERATIONS', !$isCommonProject),
                    'write' => (bool) $env->get('MCP_FILE_WRITE', true),
                    'apply-patch' => (bool) $env->get('MCP_FILE_APPLY_PATCH', false),
                    'directories-list' => (bool) $env->get('MCP_FILE_DIRECTORIES_LIST', true),
                ],
                'context_operations' => [
                    'enable' => (bool) $env->get('MCP_CONTEXT_OPERATIONS', !$isCommonProject),
                ],
                'docs_tools' => [
                    'enable' => (bool) $env->get('MCP_DOCS_TOOLS_ENABLED', true),
                ],
                'prompt_operations' => [
                    'enable' => (bool) $env->get('MCP_PROMPT_OPERATIONS', false),
                ],
                'custom_tools' => [
                    'enable' => (bool) $env->get('MCP_CUSTOM_TOOLS_ENABLE', true),
                    'max_runtime' => (int) $env->get('MCP_TOOL_MAX_RUNTIME', 30),
                ],
                'common_prompts' => [
                    'enable' => (bool) $env->get('MCP_COMMON_PROMPTS', true),
                ],
                'git_operations' => [
                    'enable' => (bool) $env->get('MCP_GIT_OPERATIONS', !$isCommonProject),
                ],
                'project_operations' => [
                    'enable' => (bool) $env->get('MCP_PROJECT_OPERATIONS', true),
                ],
            ],
        );
    }

    public function boot(ConsoleBootloader $console): void
    {
        $console->addCommand(MCPServerCommand::class);
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // MCP Handlers
            PromptsHandlerInterface::class => PromptsHandler::class,
            ResourcesHandlerInterface::class => ResourcesHandler::class,
            ToolsHandlerInterface::class => ToolsHandler::class,

            // Server infrastructure
            RunnerInterface::class => function (
                McpConfig $config,
                Runner $factory,
                ConfigLoaderInterface $loader,
            ) {
                $loader->load();

                foreach ($this->actions($config) as $action) {
                    $factory->registerAction($action);
                }

                return $factory;
            },
            RouteRegistrar::class => RouteRegistrar::class,
            McpItemsRegistry::class => McpItemsRegistry::class,
            StrategyInterface::class => McpResponseStrategy::class,
            ProjectServiceInterface::class => new ConfigProxy(
                interface: ProjectServiceInterface::class,
            ),
            Router::class => static function (StrategyInterface $strategy, #[Proxy] ContainerInterface $container) {
                $router = new Router();
                \assert($strategy instanceof McpResponseStrategy);
                $strategy->setContainer($container);
                $router->setStrategy($strategy);

                return $router;
            },
        ];
    }

    private function actions(McpConfig $config): array
    {
        $actions = [
            // Prompts controllers
            GetPromptAction::class,
            ListPromptsAction::class,

            // Resources controllers
            ListResourcesAction::class,
            GetDocumentContentResourceAction::class,

            // Tools controllers
            ListToolsAction::class,
        ];

        if ($config->commonPromptsEnabled()) {
            $actions = [
                ProjectStructurePromptAction::class,
                FilesystemOperationsAction::class,
                ...$actions,
                JsonSchemaResourceAction::class,
            ];
        }

        if ($config->isPromptOperationsEnabled()) {
            $actions = [
                ...$actions,
                GetPromptToolAction::class,
                ListPromptsToolAction::class,
            ];
        }

        if ($config->isContextOperationsEnabled()) {
            $actions = [
                ...$actions,
                ContextRequestAction::class,
                ContextGetAction::class,
                ContextAction::class,
            ];
        }

        if ($config->isDocsToolsEnabled()) {
            $actions = [
                ...$actions,
                LibrarySearchAction::class,
                FetchLibraryDocsAction::class,
            ];
        }

        if ($config->isFileOperationsEnabled()) {
            $actions = [
                ...$actions,
                FileReadAction::class,
                FileMoveAction::class,
            ];

            if ($config->isFileDirectoriesListEnabled()) {
                $actions[] = DirectoryListAction::class;
            }

            if ($config->isFileApplyPatchEnabled()) {
                $actions[] = FileApplyPatchAction::class;
            }

            if ($config->isFileWriteEnabled()) {
                $actions[] = FileWriteAction::class;
            }
        }

        if ($config->isProjectOperationsEnabled()) {
            $actions = [
                ...$actions,
                ProjectsListToolAction::class,
                ProjectSwitchToolAction::class,
            ];
        }

        if ($config->isGitOperationsEnabled()) {
            $actions[] = GitStatusAction::class;
            $actions[] = GitAddAction::class;
            $actions[] = GitCommitAction::class;
        }

        if ($config->isCustomToolsEnabled()) {
            $actions = [
                ...$actions,
                ExecuteCustomToolAction::class,
            ];
        }

        return $actions;
    }
}
