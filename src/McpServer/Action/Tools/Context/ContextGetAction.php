<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Context;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\Dto\ContextGetRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'context-get',
    description: 'Get a specific context document from the project context config',
    title: 'Get Context by path',
)]
#[InputSchema(class: ContextGetRequest::class)]
final readonly class ContextGetAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ConfigLoaderInterface $configLoader,
        private DocumentCompiler $documentCompiler,
    ) {}

    #[Post(path: '/tools/call/context-get', name: 'tools.context.get')]
    public function __invoke(ContextGetRequest $request): CallToolResult
    {
        $this->logger->info('Processing context-get tool');

        // Get params from the parsed body for POST requests
        $path = $request->path;

        if (empty($path)) {
            return ToolResult::error('Missing path parameter');
        }

        try {
            $config = new ConfigRegistryAccessor($this->configLoader->load());

            foreach ($config->getDocuments() as $document) {
                if ($document->outputPath === $path) {
                    $content = (string) $this->documentCompiler->buildContent(new ErrorCollection(), $document)->content;

                    // Return all documents in JSON format
                    return ToolResult::text($content);
                }
            }

            // Return all documents in JSON format
            return ToolResult::error(\sprintf("Document with path '%s' not found", $path));
        } catch (\Throwable $e) {
            $this->logger->error('Error getting context', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            // Return all documents in JSON format
            return ToolResult::error($e->getMessage());
        }
    }
}
