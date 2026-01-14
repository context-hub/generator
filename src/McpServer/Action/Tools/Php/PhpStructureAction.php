<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Php\Dto\PhpStructureRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for analyzing PHP file structure and relationships.
 *
 * Outputs PHP files as interface-style signatures with relationship links,
 * making it easy to understand class hierarchy without full source code.
 */
#[Tool(
    name: 'php-structure',
    description: 'Analyze PHP file structure and relationships. Returns class/interface/trait signatures with links to related files (extends, implements, use statements, type hints). Use depth parameter to follow relationships recursively.',
    title: 'PHP Structure Analyzer',
)]
#[InputSchema(class: PhpStructureRequest::class)]
final readonly class PhpStructureAction
{
    public function __construct(
        private PhpStructureHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/php-structure', name: 'tools.php-structure')]
    public function __invoke(PhpStructureRequest $request): CallToolResult
    {
        $this->logger->info('Processing php-structure tool', [
            'path' => $request->path,
            'depth' => $request->depth,
            'showPrivate' => $request->showPrivate,
        ]);

        // Validate path has .php extension
        if (!\str_ends_with(\strtolower($request->path), '.php')) {
            return ToolResult::error('Path must be a PHP file (.php extension)');
        }

        try {
            $results = $this->handler->analyze(
                path: $request->path,
                depth: $request->depth,
                showPrivate: $request->showPrivate,
            );

            if (empty($results)) {
                return ToolResult::error('No results found');
            }

            $output = $this->handler->format($results);

            return ToolResult::text($output);
        } catch (\Throwable $e) {
            $this->logger->error('PHP structure analysis error', [
                'path' => $request->path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }
}
