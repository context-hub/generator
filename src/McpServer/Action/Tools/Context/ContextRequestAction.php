<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context;

use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\Dto\ContextRequestRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'context-request',
    description: 'Request a context document using JSON schema, filters and modifiers',
    title: 'Request Context by JSON',
)]
#[InputSchema(class: ContextRequestRequest::class)]
final readonly class ContextRequestAction
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentCompiler $documentCompiler,
        private ConfigurationProvider $provider,
    ) {}

    #[Post(path: '/tools/call/context-request', name: 'tools.context.request')]
    public function __invoke(ContextRequestRequest $request): CallToolResult
    {
        $this->logger->info('Handling context-request action');

        // Get the json parameter from POST body
        $json = $request->json;

        if (empty($json)) {
            return ToolResult::error('Missing JSON parameter');
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

            return ToolResult::error($e->getMessage());
        }
    }
}
