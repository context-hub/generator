<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'context',
    description: 'List all contexts in the project context config',
)]
final readonly class ContextAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
    ) {}

    #[Post(path: '/tools/call/context', name: 'tools.context.list')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing context tool');

        try {
            $config = new ConfigRegistryAccessor($this->configLoader->load());

            $content = [];
            foreach ($config->getDocuments() as $document) {
                $content[] = new TextContent(
                    text: \json_encode($document->jsonSerialize()),
                );
            }

            // Return all documents in JSON format
            return new CallToolResult($content);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing contexts', [
                'error' => $e->getMessage(),
            ]);

            // Return all documents in JSON format
            return new CallToolResult([
                new TextContent('Error: ' . $e->getMessage()),
            ], isError: true);
        }
    }
}
