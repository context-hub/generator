<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TokenCounter;

/**
 * Interface for counting tokens in files and directories
 */
interface TokenCounterInterface
{
    /**
     * Count tokens in a file
     *
     * @param string $filePath Path to the file
     * @return int Number of tokens
     */
    public function countFile(string $filePath): int;

    /**
     * Calculate total characters in a directory structure
     *
     * @param array<string, mixed> $directory Directory structure
     * @return int Total number of characters
     */
    public function calculateDirectoryCount(array $directory): int;
}
