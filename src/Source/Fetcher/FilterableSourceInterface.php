<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Fetcher;

/**
 * Interface for filterable sources
 *
 * This interface defines methods for getting filter criteria from sources
 */
interface FilterableSourceInterface
{
    /**
     * Get file name pattern(s)
     * @return string|array<string>|null Pattern(s) to match file names against
     */
    public function name(): string|array|null;

    /**
     * Get file path pattern(s)
     * @return string|array<string>|null Pattern(s) to match file paths against
     */
    public function path(): string|array|null;

    /**
     * Get excluded path pattern(s)
     * @return string|array<string>|null Pattern(s) to exclude file paths
     */
    public function notPath(): string|array|null;

    /**
     * Get content pattern(s)
     * @return string|array<string>|null Pattern(s) to match file content against
     */
    public function contains(): string|array|null;

    /**
     * Get excluded content pattern(s)
     * @return string|array<string>|null Pattern(s) to exclude file content
     */
    public function notContains(): string|array|null;

    /**
     * Get size constraint(s)
     * @return string|array<string>|null Size constraint(s)
     */
    public function size(): string|array|null;

    /**
     * Get date constraint(s)
     * @return string|array<string>|null Date constraint(s)
     */
    public function date(): string|array|null;

    /**
     * Get directories to search in
     * @return array<string>|null Directories to search in
     */
    public function in(): array|null;

    /**
     * Get individual files to include
     * @return array<string>|null Individual files to include
     */
    public function files(): array|null;

    /**
     * Check if unreadable directories should be ignored
     */
    public function ignoreUnreadableDirs(): bool;

    /**
     * Get maximum number of files to return
     * @return non-negative-int Maximum number of files (0 for no limit)
     */
    public function maxFiles(): int;
}
