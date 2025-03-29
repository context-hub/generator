<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\FilesInterface;

/**
 * Custom implementation of FilesInterface for testing
 */
final class TestFiles implements FilesInterface
{
    private array $existingFiles = [];
    private array $wildcardMatches = [];

    public function addExistingFile(string $path): void
    {
        $this->existingFiles[] = $path;
    }

    public function addFilesForWildcard(string $pattern, array $matches): void
    {
        $this->wildcardMatches[$pattern] = $matches;
    }

    public function exists(string $path): bool
    {
        return \in_array($path, $this->existingFiles, true);
    }

    /**
     * Support for WildcardPathFinder
     */
    public function allFiles(?string $directory = null): array
    {
        $files = [];
        foreach ($this->existingFiles as $file) {
            if ($directory === null || \str_starts_with((string) $file, $directory)) {
                $files[] = $file;
            }
        }
        return $files;
    }

    // Other required methods from FilesInterface
    public function get(string $path): string
    {
        return 'File content';
    }

    public function put(string $path, string $contents): bool
    {
        return true;
    }

    public function isDirectory(string $directory): bool
    {
        return false;
    }

    public function isFile(string $file): bool
    {
        return $this->exists($file);
    }

    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false): bool
    {
        return true;
    }

    public function glob(string $pattern, int $flags = 0): array
    {
        return $this->wildcardMatches[$pattern] ?? [];
    }

    public function delete(array|string $paths): bool
    {
        return true;
    }

    public function copy(string $from, string $to): bool
    {
        return true;
    }

    public function isWritable(string $path): bool
    {
        return true;
    }

    public function isReadable(string $path): bool
    {
        return $this->exists($path);
    }

    public function size(string $path): int
    {
        return 100;
    }

    public function lastModified(string $path): int
    {
        return \time();
    }

    public function files(string $directory, bool $recursive = false): array
    {
        return $this->allFiles($directory);
    }

    public function directories(string $directory, bool $recursive = false): array
    {
        return [];
    }

    public function fileList(string $directory, bool $recursive = false): array
    {
        return $this->allFiles($directory);
    }

    public function move(string $from, string $to): bool
    {
        return true;
    }

    public function getRequire(string $path, array $data = []): mixed
    {
        return [];
    }

    public function ensureDirectory(string $directory): bool
    {
        // TODO: Implement ensureDirectory() method.
    }

    public function write(string $filename, string $content, bool $lock = true): bool
    {
        // TODO: Implement write() method.
    }

    public function read(string $filename): string|false
    {
        // TODO: Implement read() method.
    }
}
