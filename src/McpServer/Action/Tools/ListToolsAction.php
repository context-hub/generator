<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Butschster\ContextGenerator\McpServer\Tool\ToolProviderInterface;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputSchema;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListToolsAction
{
    public function __construct(
        private LoggerInterface $logger,
        private McpItemsRegistry $registry,
        private ToolProviderInterface $toolProvider,
    ) {}

    #[Get(path: '/tools/list', name: 'tools.list')]
    public function __invoke(ServerRequestInterface $request): ListToolsResult
    {
        $this->logger->info('Listing available tools');

        $tools = $this->registry->getTools();
        foreach ($this->toolProvider->all() as $toolDefinition) {
            $tools[] = new Tool(
                name: $toolDefinition->id,
                inputSchema: new ToolInputSchema(),
                description: $toolDefinition->description,
            );
        }

        return new ListToolsResult($tools);
    }
}
