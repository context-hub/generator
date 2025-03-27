<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\ListToolsResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListToolsAction
{
    public function __construct(
        private LoggerInterface $logger,
        private McpItemsRegistry $registry,
    ) {}

    #[Get(path: '/tools/list', name: 'tools.list')]
    public function __invoke(ServerRequestInterface $request): ListToolsResult
    {
        $this->logger->info('Listing available tools');

        return new ListToolsResult($this->registry->getTools());
    }
}
