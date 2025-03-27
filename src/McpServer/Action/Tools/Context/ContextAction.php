<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ContextAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
    ) {}

    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing context tool');

        try {
            $documents = $this->configLoader->load();

            $content = [];
            foreach ($documents->getItems() as $document) {
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
