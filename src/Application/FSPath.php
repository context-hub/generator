<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

final class FSPath implements \Stringable
{
    /**
     * Override for the directory separator - used for testing only
     */
    private static ?string $overriddenDirectorySeparator = null;

    /**
     * @param string $path The filesystem path
     */
    private function __construct(
        private readonly string $path,
    ) {}

    /**
     * Create a new path object
     */
    public static function create(string $path = ''): self
    {
        return new self(self::normalizePath($path));
    }

    /**
     * Create a path object representing the current working directory
     */
    public static function cwd(): self
    {
        return new self(\getcwd() ?: '.');
    }

    /**
     * Create temporary directory
     */
    public static function temp(): self
    {
        return new self(\sys_get_temp_dir());
    }

    /**
     * Get the directory separator - can be overridden for testing
     */
    public static function getDirectorySeparator(): string
    {
        return self::$overriddenDirectorySeparator ?? \DIRECTORY_SEPARATOR;
    }

    /**
     * Set an override for the directory separator - for testing only
     */
    public static function setDirectorySeparator(?string $separator): void
    {
        self::$overriddenDirectorySeparator = $separator;
    }

    /**
     * Join this path with one or more path components
     */
    public function join(string ...$paths): self
    {
        $result = !$this->isFile() ? $this->path : (string) $this->parent();

        foreach ($paths as $path) {
            if (empty($path)) {
                continue;
            }

            if (self::_isAbsolute($path)) {
                $result = $path;
                continue;
            }

            if ($result !== '' && !\str_ends_with(haystack: $result, needle: self::getDirectorySeparator())) {
                $result .= self::getDirectorySeparator();
            }

            $result .= \ltrim(string: $path, characters: self::getDirectorySeparator());
        }

        // We return the raw string, not a normalized path, since it's already normalized
        return new self(self::normalizePath($result));
    }

    /**
     * Return a new path with the file name changed
     */
    public function withName(string $name): self
    {
        if ($this->isRoot()) {
            return $this;
        }

        return $this->parent()->join($name);
    }

    /**
     * Return a new path with the extension changed
     */
    public function withExt(string $suffix): self
    {
        if ($this->isRoot()) {
            return $this;
        }

        $stem = $this->stem();

        if (!\str_starts_with(haystack: $suffix, needle: '.') && !empty($suffix)) {
            $suffix = '.' . $suffix;
        }

        return $this->withName(name: $stem . $suffix);
    }

    /**
     * Return a new path with the stem changed (file name without extension)
     */
    public function withStem(string $stem): self
    {
        if ($this->isRoot()) {
            return $this;
        }

        $suffix = $this->extension();
        // Fix: Add period before extension if it's not empty
        if (!empty($suffix)) {
            $suffix = '.' . $suffix;
        }

        return $this->withName(name: $stem . $suffix);
    }

    /**
     * Return the file name (the final path component)
     */
    public function name(): string
    {
        $parts = $this->parts();

        // If there are no parts, return the current directory
        if (empty($parts)) {
            return '';
        }

        // If the path is a single part, return the current directory
        if (\count(value: $parts) === 1) {
            return $parts[0];
        }

        // get the last part of the path
        return \array_pop(array: $parts);
    }

    /**
     * Return the file stem (the file name without its extension)
     */
    public function stem(): string
    {
        $name = $this->name();
        $pos = \strrpos(haystack: $name, needle: '.');

        if ($pos === false || $pos === 0) {
            return $name;
        }

        return \substr(string: $name, offset: 0, length: $pos);
    }

    /**
     * Return the file suffix (extension)
     */
    public function extension(): string
    {
        $name = $this->name();

        return \pathinfo(path: $name, flags: PATHINFO_EXTENSION);
    }

    /**
     * Return the parent directory path
     */
    public function parent(): self
    {
        // If this is a root path, return it unchanged
        if ($this->isRoot()) {
            return $this;
        }

        $parts = $this->parts();
        // If there are no parts, return the current directory
        if (empty($parts)) {
            return self::create('.');
        }

        // If the path is a single part, return the current directory
        if (\count(value: $parts) === 1) {
            return self::create('.');
        }

        // Remove the last part to get the parent directory
        $parts = \array_slice(array: $parts, offset: 0, length: -1);
        $isAbsPath = \str_starts_with(haystack: $this->path, needle: self::getDirectorySeparator());

        // If the path is absolute, ensure the first part is preserved
        $path = \implode(separator: self::getDirectorySeparator(), array: $parts);
        if ($isAbsPath) {
            $path = self::getDirectorySeparator() . $path;
        }

        // Use dirname for a simple, reliable solution
        return new self($path);
    }

    /**
     * Return an array of the path's components
     */
    public function parts(): array
    {
        $normalizedPath = \str_replace(search: ['\\', '/'], replace: self::getDirectorySeparator(), subject: $this->path);
        return \array_values(array: \array_filter(array: \explode(separator: self::getDirectorySeparator(), string: $normalizedPath), callback: \strlen(...)));
    }

    /**
     * Return whether this path is absolute
     */
    public function isAbsolute(): bool
    {
        return self::_isAbsolute($this->path);
    }

    /**
     * Return whether this path is relative
     */
    public function isRelative(): bool
    {
        return !$this->isAbsolute();
    }

    /**
     * Check if the path exists
     */
    public function exists(): bool
    {
        return \file_exists(filename: $this->path);
    }

    /**
     * Check if the path is a directory
     */
    public function isDir(): bool
    {
        return \is_dir(filename: $this->path);
    }

    /**
     * Check if the path is a file
     */
    public function isFile(): bool
    {
        return \is_file(filename: $this->path);
    }

    /**
     * Return a new path that is a relative path from the given path to this path
     */
    public function relativeTo(self $other): self
    {
        // If paths are on different drives (Windows), return the absolute path
        if (self::getDirectorySeparator() === '\\') {
            $thisRoot = $this->_getWindowsDrive(path: $this->path);
            $otherRoot = $this->_getWindowsDrive(path: $other->path);

            if ($thisRoot !== $otherRoot) {
                return $this;
            }
        }

        // Normalize both paths for comparison
        $thisPath = $this->path;
        $otherPath = $other->path;

        // If paths are the same, return current directory
        if ($thisPath === $otherPath) {
            return self::create('.');
        }

        // Split paths into parts
        $thisParts = $this->parts();
        $otherParts = $other->parts();

        // Find the common prefix
        $commonLength = 0;
        $minLength = \min(\count(value: $thisParts), \count(value: $otherParts));

        while ($commonLength < $minLength && $thisParts[$commonLength] === $otherParts[$commonLength]) {
            $commonLength++;
        }

        // Build the relative path
        $relParts = [];

        // Add '..' for each directory level to go up
        $upCount = \count(value: $otherParts) - $commonLength;
        if ($upCount > 0) {
            $relParts = \array_fill(start_index: 0, count: $upCount, value: '..');
        }

        // Add path components to go down
        if ($commonLength < \count(value: $thisParts)) {
            $relParts = [...$relParts, ...\array_slice(array: $thisParts, offset: $commonLength)];
        }

        if (empty($relParts)) {
            return self::create('.');
        }

        return self::create(\implode(separator: self::getDirectorySeparator(), array: $relParts));
    }

    /**
     * Return a normalized absolute version of this path
     */
    public function absolute(): self
    {
        if ($this->isAbsolute()) {
            return $this;
        }

        return self::cwd()->join($this->path);
    }

    /**
     * Trim the path to the given path
     */
    public function trim(string $path): self
    {
        return self::create(\substr(string: \str_replace(search: $path, replace: '', subject: $this->toString()), offset: 1));
    }

    /**
     * Return the string representation of this path
     */
    public function toString(): string
    {
        return $this->path;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Check if a path is absolute
     */
    private static function _isAbsolute(string $path): bool
    {
        // Windows absolute path
        if (self::getDirectorySeparator() === '\\') {
            // Drive letter, UNC path, or absolute path
            return (bool) \preg_match(pattern: '~^(?:[a-zA-Z]:(?:\\\\|/)|\\\\\\\\|/)~', subject: $path);
        }

        // Unix-like absolute path
        return \str_starts_with(haystack: $path, needle: '/');
    }

    /**
     * Normalize a path by converting directory separators and resolving special path segments
     */
    private static function normalizePath(string $path): string
    {
        // Normalize directory separators
        $path = \str_replace(search: ['\\', '/'], replace: self::getDirectorySeparator(), subject: $path);

        // Normalize multiple separators
        $path = \preg_replace(
            pattern: '~' . \preg_quote(str: self::getDirectorySeparator(), delimiter: '~') . '{2,}~',
            replacement: self::getDirectorySeparator(),
            subject: $path,
        );

        // Empty path becomes current directory
        if ($path === '') {
            return '.';
        }

        // Extract Windows drive letter if present
        $driveLetter = '';
        $isWindowsPath = false;
        if (self::getDirectorySeparator() === '\\' && \preg_match(pattern: '~^([a-zA-Z]:)~', subject: (string) $path, matches: $matches) === 1) {
            $driveLetter = $matches[1];
            $path = \substr(string: (string) $path, offset: 2); // Remove drive letter for normalization
            $isWindowsPath = true;
        }

        // Determine if the path is absolute
        $isAbsolute = self::_isAbsolute($path);

        /**
         * Resolve special path segments
         * @psalm-suppress RedundantCast
         */
        $parts = \array_filter(
            array: \explode(separator: self::getDirectorySeparator(), string: (string) $path),
            callback: static fn($part) => $part !== '',
        );
        $result = [];

        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }

            if ($part === '..') {
                if (!empty($result)) {
                    \array_pop(array: $result);
                } elseif (!$isAbsolute) {
                    $result[] = '..';
                }
                continue;
            }

            $result[] = $part;
        }

        // Reconstruct the path
        $normalizedPath = \implode(separator: self::getDirectorySeparator(), array: $result);

        // Handle Windows paths specifically
        if ($isWindowsPath) {
            // For Windows, construct with drive letter and separator
            if (empty($normalizedPath)) {
                // If path is just the root (e.g., C:\)
                return $driveLetter . self::getDirectorySeparator();
            }

            // For normal Windows paths (e.g., C:\Users\test)
            return $driveLetter . self::getDirectorySeparator() . $normalizedPath;
        }

        // Handle Unix paths or Windows paths without drive letters
        if ($isAbsolute) {
            $normalizedPath = self::getDirectorySeparator() . $normalizedPath;
        }

        return $normalizedPath ?: '.';
    }

    /**
     * Return whether this path is the root directory
     */
    private function isRoot(): bool
    {
        $normalized = $this->path;

        // Unix-like root
        if ($normalized === '/') {
            return true;
        }

        // Windows root (C:\ or similar)
        if (self::getDirectorySeparator() === '\\' && \preg_match(pattern: '~^[a-zA-Z]:[\\\\/]?$~', subject: $normalized)) {
            return true;
        }

        return false;
    }

    /**
     * Extract Windows drive letter if present
     */
    private function _getWindowsDrive(string $path): string
    {
        if (\preg_match(pattern: '~^([a-zA-Z]:)~', subject: $path, matches: $matches)) {
            return $matches[1];
        }

        return '';
    }
}
