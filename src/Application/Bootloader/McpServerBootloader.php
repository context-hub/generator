<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Console\MCPServerCommand;
use Butschster\ContextGenerator\McpServer\Action\Prompts\AvailableContextPromptAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\FilesystemOperationsAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\ListPromptsAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\ProjectStructurePromptAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\GetDocumentContentResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\JsonSchemaResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\ListResourcesAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextGetAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextRequestAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileInfoAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileMoveAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReadAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRenameAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWriteAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\ListToolsAction;
use Butschster\ContextGenerator\McpServer\McpConfig;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\McpResponseStrategy;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Butschster\ContextGenerator\McpServer\ServerFactory;
use League\Route\Router;
use League\Route\Strategy\StrategyInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;

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
        ];
    }

    public function init(EnvironmentInterface $env): void
    {
        $this->config->setDefaults(
            McpConfig::CONFIG,
            [
                'ctx_document_name_format' => $env->get('MCP_DOCUMENT_NAME_FORMAT', '[{path}] {description}'),
                'file_operations' => [
                    'enable' => (bool) $env->get('MCP_FILE_OPERATIONS', true),
                ],
            ],
        );
    }

    public function boot(ConsoleBootloader $bootloader): void
    {
        $bootloader->addCommand(MCPServerCommand::class);
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ServerFactory::class => function (
                RouteRegistrar $registrar,
                McpItemsRegistry $registry,
                McpConfig $config,
            ) {
                $factory = new ServerFactory($registrar, $registry);

                foreach ($this->actions($config) as $action) {
                    $factory->registerAction($action);
                }

                return $factory;
            },
            McpItemsRegistry::class => McpItemsRegistry::class,
            RouteRegistrar::class => RouteRegistrar::class,
            StrategyInterface::class => McpResponseStrategy::class,
            Router::class => static function (
                StrategyInterface $strategy,
                ContainerInterface $container,
            ) {
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
            AvailableContextPromptAction::class,
            ProjectStructurePromptAction::class,
            FilesystemOperationsAction::class,
            ListPromptsAction::class,

            // Resources controllers
            ListResourcesAction::class,
            JsonSchemaResourceAction::class,
            GetDocumentContentResourceAction::class,

            // Tools controllers
            ListToolsAction::class,
            ContextRequestAction::class,
            ContextGetAction::class,
            ContextAction::class,
        ];

        if ($config->isFileOperationsEnabled()) {
            $actions = [
                ...$actions,
                FileInfoAction::class,
                FileReadAction::class,
                FileWriteAction::class,
                FileRenameAction::class,
                FileMoveAction::class,
            ];
        }

        return $actions;
    }
}
