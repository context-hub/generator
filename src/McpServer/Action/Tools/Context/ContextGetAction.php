<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'context-get',
    description: 'Get a specific context document from the project context config',
)]
#[InputSchema(
    name: 'path',
    type: 'string',
    description: 'Output path to the context document provided in the config.',
    required: true,
)]
final readonly class ContextGetAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private DocumentCompiler $documentCompiler,
    ) {}

    #[Post(path: '/tools/call/context-get', name: 'tools.context.get')]
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
            $config = new ConfigRegistryAccessor($this->configLoader->load());

            foreach ($config->getDocuments() as $document) {
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
