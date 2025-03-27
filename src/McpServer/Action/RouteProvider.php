<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action;

use Butschster\ContextGenerator\McpServer\Action\Prompts\GetPromptAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\ListPromptsAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\GetDocumentContentResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\GetJsonSchemaResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\ListDocumentsResourceAction;
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
use Butschster\ContextGenerator\McpServer\Routing\McpResponseStrategy;
use League\Route\Router;
use Psr\Container\ContainerInterface;

final readonly class RouteProvider
{
    public function __construct(
        private ContainerInterface $container,
        private McpResponseStrategy $strategy,
    ) {}

    /**
     * Register all routes for MCP
     */
    public function registerRoutes(): Router
    {
        $router = new Router();

        $this->strategy->setContainer($this->container);
        $router->setStrategy($this->strategy);

        // Prompts routes
        $router->get('/prompts/list', $this->container->get(ListPromptsAction::class));
        $router->get('/prompt/{name}', $this->container->get(GetPromptAction::class));

        // Resources routes
        $router->get('/resources/list', $this->container->get(ListResourcesAction::class));
        $router->get('/resource/ctx/list', $this->container->get(ListDocumentsResourceAction::class));
        $router->get('/resource/ctx/json-schema', $this->container->get(GetJsonSchemaResourceAction::class));
        $router->get(
            '/resource/ctx/document/{path:.*}',
            $this->container->get(GetDocumentContentResourceAction::class),
        );

        // Tools routes
        $router->get('/tools/list', $this->container->get(ListToolsAction::class));
        $router->post('/tools/call/context-request', $this->container->get(ContextRequestAction::class));
        $router->post('/tools/call/context-get', $this->container->get(ContextGetAction::class));
        $router->post('/tools/call/context', $this->container->get(ContextAction::class));

        // filesystem tool routes
        $router->post('/tools/call/file-read', $this->container->get(FileReadAction::class));
        $router->post('/tools/call/file-write', $this->container->get(FileWriteAction::class));
        $router->post('/tools/call/file-rename', $this->container->get(FileRenameAction::class));
        $router->post('/tools/call/file-move', $this->container->get(FileMoveAction::class));
        $router->post('/tools/call/file-info', $this->container->get(FileInfoAction::class));

        return $router;
    }
}
