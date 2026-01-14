<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileSearch\Dto\FileSearchRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * MCP tool for searching file contents by text or regex patterns.
 */
#[Tool(
    name: 'file-search',
    description: 'Search for text or regex patterns in files. Returns matches with surrounding context lines and line numbers. Useful for finding code patterns, function definitions, or specific content across the codebase.',
    title: 'File Search',
)]
#[InputSchema(class: FileSearchRequest::class)]
final readonly class FileSearchAction
{
    public function __construct(
        private FileSearchHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/file-search', name: 'tools.file-search')]
    public function __invoke(FileSearchRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-search tool', [
            'query' => $request->query,
            'path' => $request->path,
            'pattern' => $request->pattern,
            'regex' => $request->regex,
        ]);

        // Validate query
        if (\trim($request->query) === '') {
            return $this->error('Search query cannot be empty');
        }

        // Validate regex pattern if regex mode
        if ($request->regex) {
            $testPattern = $request->buildPattern();
            if (@\preg_match($testPattern, '') === false) {
                return $this->error(\sprintf(
                    'Invalid regex pattern: %s',
                    \preg_last_error_msg(),
                ));
            }
        }

        try {
            $results = $this->handler->search($request);

            return $this->formatResults($results, $request);
        } catch (\Throwable $e) {
            $this->logger->error('File search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage());
        }
    }

    /**
     * @param FileSearchResult[] $results
     */
    private function formatResults(array $results, FileSearchRequest $request): CallToolResult
    {
        $totalMatches = 0;
        $filesWithMatches = 0;
        $truncatedFiles = 0;

        foreach ($results as $result) {
            if ($result->success && $result->getMatchCount() > 0) {
                $filesWithMatches++;
                $totalMatches += $result->getMatchCount();
                if ($result->truncated) {
                    $truncatedFiles++;
                }
            }
        }

        if ($totalMatches === 0) {
            return ToolResult::text(\sprintf(
                "No matches found for %s'%s' in %s",
                $request->regex ? 'pattern ' : '',
                $request->query,
                $request->path !== '' ? $request->path : 'project root',
            ));
        }

        $output = [];
        $output[] = \sprintf(
            'Found %d match%s in %d file%s',
            $totalMatches,
            $totalMatches === 1 ? '' : 'es',
            $filesWithMatches,
            $filesWithMatches === 1 ? '' : 's',
        );

        if ($truncatedFiles > 0 || $totalMatches >= $request->maxTotalMatches) {
            $output[] = '(results may be truncated due to limits)';
        }

        $output[] = '';

        foreach ($results as $result) {
            $formatted = $result->format();
            if ($formatted !== '') {
                $output[] = $formatted;
                $output[] = '';
            }
        }

        return ToolResult::text(\implode("\n", $output));
    }

    private function error(string $message): CallToolResult
    {
        return ToolResult::error($message);
    }
}
