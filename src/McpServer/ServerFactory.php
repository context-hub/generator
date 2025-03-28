<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Action\Prompts\AvailableContextPromptAction;
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
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileUpdateLinesAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWriteAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\ListToolsAction;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Psr\Log\LoggerInterface;

final readonly class ServerFactory
{
    public function __construct(
        private RouteRegistrar $registrar,
        private McpItemsRegistry $registry,
    ) {}

    /**
     * Create a new McpServer instance with attribute-based routing
     */
    public function create(LoggerInterface $logger): Server
    {
        // Define all action classes
        $actionClasses = [
            // Prompts controllers
            AvailableContextPromptAction::class,
            ProjectStructurePromptAction::class,
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

            // Filesystem controllers
            FileReadAction::class,
            FileWriteAction::class,
            FileRenameAction::class,
            FileMoveAction::class,
            FileInfoAction::class,
            FileUpdateLinesAction::class,
        ];

        // Register all classes with MCP item attributes. Should be before registering controllers!
        $this->registry->registerMany($actionClasses);

        // Register all controllers for routing
        $this->registrar->registerControllers($actionClasses);

        // Create the server
        return new Server(
            router: $this->registrar->router,
            logger: $logger,
        );
    }
}
