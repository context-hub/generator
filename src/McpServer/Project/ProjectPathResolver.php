<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\McpServer\Project\Exception\ProjectPathException;
use Psr\Log\LoggerInterface;

/**
 * Resolves and validates project paths from YAML configuration.
 *
 * Handles both relative paths (resolved from context file directory)
 * and absolute paths (used directly after validation).
 */
final readonly class ProjectPathResolver implements ProjectPathResolverInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Resolve a path to an absolute, validated path.
     *
     * @param string $path Raw path from YAML (relative or absolute)
     * @param string $contextDir Directory containing context.yaml
     * @return string Resolved absolute path
     * @throws ProjectPathException If path is invalid
     */
    public function resolve(string $path, string $contextDir): string
    {
        $this->logger?->debug('Resolving project path', [
            'path' => $path,
            'contextDir' => $contextDir,
        ]);

        // Create FSPath for the input
        $fsPath = FSPath::create($path);

        // Resolve relative paths against context directory
        if ($fsPath->isRelative()) {
            $contextPath = FSPath::create($contextDir);
            $fsPath = $contextPath->join($path);

            $this->logger?->debug('Resolved relative path', [
                'original' => $path,
                'resolved' => $fsPath->toString(),
            ]);
        }

        $resolvedPath = $fsPath->toString();

        // Validate the resolved path
        $this->validate($resolvedPath);

        $this->logger?->info('Project path resolved successfully', [
            'path' => $resolvedPath,
        ]);

        return $resolvedPath;
    }

    /**
     * Validate that a path exists, is a directory, and is readable.
     *
     * @throws ProjectPathException If validation fails
     */
    private function validate(string $path): void
    {
        if (!\file_exists($path)) {
            throw ProjectPathException::notFound($path);
        }

        if (!\is_dir($path)) {
            throw ProjectPathException::notDirectory($path);
        }

        if (!\is_readable($path)) {
            throw ProjectPathException::notReadable($path);
        }
    }
}
