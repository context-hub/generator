<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Console\MCPServerCommand;
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
use Butschster\ContextGenerator\McpServer\Action\Tools\ExecuteCustomToolAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryListAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileApplyPatchAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInfoAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileMoveAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReadAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRenameAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWriteAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\ListToolsAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\GetPromptToolAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\ListPromptsToolAction;
use Butschster\ContextGenerator\McpServer\McpConfig;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\McpResponseStrategy;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Butschster\ContextGenerator\McpServer\ServerRunner;
use Butschster\ContextGenerator\McpServer\ServerRunnerInterface;
use Butschster\ContextGenerator\McpServer\Tool\McpToolBootloader;
use League\Route\Router;
use League\Route\Strategy\StrategyInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\BinderInterface;

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
        ];
    }

    public function init(EnvironmentInterface $env): void
    {
        $this->config->setDefaults(
            McpConfig::CONFIG,
            [
                'document_name_format' => $env->get('MCP_DOCUMENT_NAME_FORMAT', '[{path}] {description}'),
                'file_operations' => [
                    'enable' => (bool) $env->get('MCP_FILE_OPERATIONS', true),
                    'write' => (bool) $env->get('MCP_FILE_WRITE', true),
                    'apply-patch' => (bool) $env->get('MCP_FILE_APPLY_PATCH', false),
                    'directories-list' => (bool) $env->get('MCP_FILE_DIRECTORIES_LIST', true),
                ],
                'context_operations' => [
                    'enable' => (bool) $env->get('MCP_CONTEXT_OPERATIONS', true),
                ],
                'prompt_operations' => [
                    'enable' => (bool) $env->get('MCP_PROMPT_OPERATIONS', false),
                ],
                'custom_tools' => [
                    'enable' => (bool) $env->get('MCP_CUSTOM_TOOLS_ENABLE', true),
                    'max_runtime' => (int) $env->get('MCP_TOOL_MAX_RUNTIME', 30),
                ],
            ],
        );
    }

    public function boot(ConsoleBootloader $bootloader, BinderInterface $binder, McpConfig $config): void
    {
        $bootloader->addCommand(MCPServerCommand::class);
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ServerRunnerInterface::class => function (
                McpConfig $config,
                ServerRunner $factory,
            ) {
                foreach ($this->actions($config) as $action) {
                    $factory->registerAction($action);
                }

                return $factory;
            },
            RouteRegistrar::class => RouteRegistrar::class,
            McpItemsRegistry::class => McpItemsRegistry::class,
            StrategyInterface::class => McpResponseStrategy::class,
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
            ProjectStructurePromptAction::class,
            FilesystemOperationsAction::class,
            GetPromptAction::class,
            ListPromptsAction::class,

            // Resources controllers
            ListResourcesAction::class,
            JsonSchemaResourceAction::class,
            GetDocumentContentResourceAction::class,

            // Tools controllers
            ListToolsAction::class,
        ];

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

        if ($config->isFileOperationsEnabled()) {
            $actions = [
                ...$actions,
                FileInfoAction::class,
                FileReadAction::class,
                FileRenameAction::class,
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

        if ($config->isCustomToolsEnabled()) {
            $actions = [
                ...$actions,
                ExecuteCustomToolAction::class,
            ];
        }

        return $actions;
    }
}
