<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ContextGetAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private DocumentCompiler $documentCompiler,
    ) {}

    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing context-get tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $path = $parsedBody['path'] ?? '';

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        try {
            $documents = $this->configLoader->load();

            foreach ($documents->getItems() as $document) {
                if ($document->outputPath === $path) {
                    $content = new TextContent(
                        text: (string) $this->documentCompiler->buildContent(new ErrorCollection(), $document)->content,
                    );

                    // Return all documents in JSON format
                    return new CallToolResult([$content]);
                }
            }

            // Return all documents in JSON format
            return new CallToolResult([
                new TextContent(
                    text: \sprintf("Error: Document with path '%s' not found", $path),
                ),
            ], isError: true);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting context', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            // Return all documents in JSON format
            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }
}
