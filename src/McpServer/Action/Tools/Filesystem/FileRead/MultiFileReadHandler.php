<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileRead;

use Psr\Log\LoggerInterface;

/**
 * Handler for reading multiple files in a single request.
 */
final readonly class MultiFileReadHandler
{
    private const int MAX_FILES_PER_REQUEST = 50;
    private const int MAX_TOTAL_RESPONSE_SIZE = 50 * 1024 * 1024; // 50 MB

    public function __construct(
        private FileReadHandler $fileHandler,
        private LoggerInterface $logger,
    ) {}

    /**
     * Read multiple files and return array of results.
     *
     * @param string[] $paths Array of file paths relative to project root
     * @param string $encoding File encoding
     * @return FileReadResult[]
     */
    public function readAll(array $paths, string $encoding = 'utf-8'): array
    {
        // Deduplicate paths while preserving order
        $uniquePaths = \array_values(\array_unique($paths));

        // Validate file count limit
        if (\count($uniquePaths) > self::MAX_FILES_PER_REQUEST) {
            $this->logger->warning('Too many files requested', [
                'requested' => \count($uniquePaths),
                'max' => self::MAX_FILES_PER_REQUEST,
            ]);

            return [
                FileReadResult::error(
                    'batch',
                    \sprintf(
                        'Too many files requested (%d). Maximum is %d files per request.',
                        \count($uniquePaths),
                        self::MAX_FILES_PER_REQUEST,
                    ),
                ),
            ];
        }

        $results = [];
        $totalSize = 0;

        foreach ($uniquePaths as $path) {
            $result = $this->fileHandler->read($path, $encoding);
            $results[] = $result;

            if ($result->success && $result->size !== null) {
                $totalSize += $result->size;

                // Check total response size limit
                if ($totalSize > self::MAX_TOTAL_RESPONSE_SIZE) {
                    $this->logger->warning('Total response size exceeded', [
                        'totalSize' => $totalSize,
                        'max' => self::MAX_TOTAL_RESPONSE_SIZE,
                    ]);

                    $results[] = FileReadResult::error(
                        'batch',
                        \sprintf(
                            'Total response size exceeded (%d bytes). Maximum is %d bytes. Remaining files skipped.',
                            $totalSize,
                            self::MAX_TOTAL_RESPONSE_SIZE,
                        ),
                    );

                    break;
                }
            }
        }

        $successCount = \count(\array_filter($results, static fn($r) => $r->success));
        $failureCount = \count($results) - $successCount;

        $this->logger->info('Batch file read complete', [
            'total' => \count($uniquePaths),
            'success' => $successCount,
            'failure' => $failureCount,
            'totalSize' => $totalSize,
        ]);

        return $results;
    }

    /**
     * Format multiple file results into a single text response.
     *
     * @param FileReadResult[] $results
     */
    public function formatResponse(array $results): string
    {
        $output = [];

        foreach ($results as $result) {
            if ($result->success) {
                $output[] = \sprintf("=== File: %s ===", $result->path);
                $output[] = $result->content;
                $output[] = '';
            } else {
                $output[] = \sprintf("=== File: %s [ERROR] ===", $result->path);
                $output[] = $result->error;
                $output[] = '';
            }
        }

        return \trim(\implode("\n", $output));
    }

    /**
     * Check if all results are errors.
     *
     * @param FileReadResult[] $results
     */
    public function allFailed(array $results): bool
    {
        if (empty($results)) {
            return true;
        }

        foreach ($results as $result) {
            if ($result->success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get aggregated error message when all files failed.
     *
     * @param FileReadResult[] $results
     */
    public function getAggregatedError(array $results): string
    {
        $errors = [];

        foreach ($results as $result) {
            if (!$result->success) {
                $errors[] = \sprintf("- %s: %s", $result->path, $result->error);
            }
        }

        return "All files failed to read:\n" . \implode("\n", $errors);
    }
}
