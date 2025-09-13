<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\ChangeChunkConfig;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\ChangeChunkProcessor;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

#[Tool(
    name: 'file-apply-patch',
    description: 'Apply change chunks to modify files using contextual markers and fuzzy matching',
    title: 'File Apply Change Chunk',
)]
#[InputSchema(class: FileApplyPatchRequest::class)]
final readonly class FileApplyPatchAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
        private ChangeChunkProcessor $processor = new ChangeChunkProcessor(),
    ) {}

    #[Post(path: '/tools/call/file-apply-patch', name: 'tools.file-apply-patch')]
    public function __invoke(FileApplyPatchRequest $request): CallToolResult
    {
        $this->logger->info('Processing file-apply-patch tool', [
            'path' => $request->path,
            'chunksCount' => \count($request->chunks),
        ]);

        $filePath = (string) $this->dirs->getRootPath()->join($request->path);

        // Validate file exists and is readable
        if (!$this->files->exists($filePath)) {
            return $this->createErrorResult(\sprintf("Error: File '%s' does not exist", $request->path));
        }

        if (\is_dir($filePath)) {
            return $this->createErrorResult(\sprintf("Error: '%s' is a directory, not a file", $request->path));
        }

        // Validate chunks are provided
        if (empty($request->chunks)) {
            return $this->createErrorResult('Error: No change chunks provided');
        }

        try {
            // Read current file content
            $originalContent = $this->files->read($filePath);
        } catch (FilesException $e) {
            $this->logger->error('Error reading file for patch application', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return $this->createErrorResult(
                \sprintf("Error: Could not read file '%s': %s", $request->path, $e->getMessage()),
            );
        }

        try {
            // Process the changes
            $result = $this->processor->processChanges($request, new ChangeChunkConfig(), $originalContent);

            if (!$result->success) {
                return $this->createErrorResult(
                    \sprintf('Failed to apply patch: %s', \implode(', ', $result->errors)),
                    $result->getDetailedReport(),
                );
            }

            // Write modified content back to file if changes were made
            if ($result->hasChanges()) {
                $writeSuccess = $this->files->write($filePath, $result->modifiedContent);

                if (!$writeSuccess) {
                    return $this->createErrorResult(
                        \sprintf("Error: Could not write modified content to '%s'", $request->path),
                    );
                }

                $this->logger->info('Successfully applied patch to file', [
                    'path' => $filePath,
                    'appliedChanges' => $result->appliedChanges,
                    'warnings' => $result->warnings,
                ]);

                return new CallToolResult([
                    new TextContent(
                        text: \json_encode([
                            'success' => true,
                            'message' => \sprintf(
                                "Successfully applied %d change chunks to '%s'",
                                \count($request->chunks),
                                $request->path,
                            ),
                            'appliedChanges' => $result->appliedChanges,
                            'warnings' => $result->warnings,
                            'summary' => $result->getSummary(),
                        ]),
                    ),
                ]);
            }
            return new CallToolResult([
                new TextContent(
                    text: \json_encode([
                        'success' => true,
                        'message' => 'No changes were needed - file content already matches target state',
                        'warnings' => $result->warnings,
                    ]),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error processing patch application', [
                'path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->createErrorResult(
                \sprintf('Error processing patch: %s', $e->getMessage()),
            );
        }
    }

    private function createErrorResult(string $message, ?array $details = null): CallToolResult
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($details !== null) {
            $response['details'] = $details;
        }

        return new CallToolResult([
            new TextContent(
                text: \json_encode($response, JSON_PRETTY_PRINT),
            ),
        ], isError: true);
    }
}
