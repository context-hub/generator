<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context;

use Butschster\ContextGenerator\Config\ConfigurationProvider;
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
    name: 'context-request',
    description: 'Request a context document using JSON schema, filters and modifiers',
)]
#[InputSchema(
    name: 'json',
    type: 'string',
    description: 'Context configuration in JSON format. It should contain the context documents with sources (file, tree, text) and any filters or modifiers to be applied.',
    required: true,
)]
final readonly class ContextRequestAction
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentCompiler $documentCompiler,
        private ConfigurationProvider $provider,
    ) {}

    #[Post(path: '/tools/call/context-request', name: 'tools.context.request')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Handling context-request action');

        // Get the json parameter from POST body
        $parsedBody = $request->getParsedBody();
        $json = $parsedBody['json'] ?? '';

        if (empty($json)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Missing JSON parameter',
                ),
            ], isError: true);
        }

        try {
            $loader = $this->provider->fromString($json);
            $config = new ConfigRegistryAccessor($loader->load());
            $compiledDocuments = [];

            foreach ($config->getDocuments() as $document) {
                $compiledDocuments[] = new TextContent(
                    text: (string) $this->documentCompiler->buildContent(new ErrorCollection(), $document)->content,
                );
            }

            return new CallToolResult($compiledDocuments);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing context request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: \sprintf('Error: %s', $e->getMessage()),
                ),
            ], isError: true);
        }
    }
}
