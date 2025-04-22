<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

final class FSPath implements \Stringable
{
    private const DS = '/';

    /**
     * @param non-empty-string $path The filesystem path. In never ends with a separator.
     *        Might be ended with "." or ".." if the path is a directory.
     */
    private function __construct(
        private readonly string $path,
        private readonly bool $isAbsolute,
    ) {}

    /**
     * Create a new path object
     */
    public static function create(self|string $path = ''): self
    {
        return $path instanceof self
            ? $path
            : new self($norm = self::normalizePath($path), self::_isAbsolute($norm));
    }

    /**
     * Create a path object representing the current working directory
     */
    public static function cwd(): self
    {
        return self::create(\getcwd() ?: '.');
    }

    /**
     * Join this path with one or more path components
     */
    public function join(self|string ...$paths): self
    {
        $result = $this->path;

        foreach ($paths as $path) {
            if ($path instanceof self) {
                $path->isAbsolute and throw new \LogicException('Joining an absolute path is not allowed.');
                $result .= self::DS . $path->path;
                continue;
            }

            if ($path === '') {
                continue;
            }

            $path = self::normalizePath($path);
            self::_isAbsolute($path) and throw new \LogicException('Joining an absolute path is not allowed.');

            $result .= self::DS . $path;
        }

        // We return the raw string, not a normalized path, since it's already normalized
        return self::create($result);
    }

    /**
     * Return the file name (the final path component)
     */
    public function name(): string
    {
        $pos = \strrpos($this->path, self::DS);
        return $pos === false
            ? $this->path
            : \substr($this->path, $pos + 1);
    }

    /**
     * Return the file stem (the file name without its extension)
     */
    public function stem(): string
    {
        $name = $this->name();
        $pos = \strrpos($name, '.');
        return $pos === false || $pos === 0 ? $name : \substr($name, 0, $pos);
    }

    /**
     * Return the file suffix (extension) without the leading dot
     */
    public function extension(): string
    {
        $name = $this->name();

        return \pathinfo($name, PATHINFO_EXTENSION);
    }

    /**
     * Return the parent directory path
     */
    public function parent(): self
    {
        $parts = \explode(self::DS, $this->path);

        if (\count($parts) === 1) {
            return match ($this->path) {
                '.' => self::create('..'),
                '..' => self::create('../..'),
                default => self::create('.'),
            };
        }

        if ($this->isAbsolute && \count($parts) === 2) {
            // If the path is absolute and has only two parts, return the root
            return self::create($parts[0] . self::DS);
        }

        if (!$this->isAbsolute && $parts[\array_key_last($parts)] === '..') {
            return $this->join('..');
        }

        // Remove the last part of the path
        \array_pop($parts);
        return self::create(\implode(self::DS, $parts));
    }

    /**
     * Return whether this path is absolute
     */
    public function isAbsolute(): bool
    {
        return $this->isAbsolute;
    }

    /**
     * Return whether this path is relative
     */
    public function isRelative(): bool
    {
        return !$this->isAbsolute;
    }

    /**
     * Check if the path exists
     */
    public function exists(): bool
    {
        return \file_exists($this->path);
    }

    /**
     * Check if the path is a directory.
     * True as the result doesn't mean that the directory exists.
     */
    public function isDir(): bool
    {
        return match (true) {
            $this->path === '.',
                $this->path === '..',
                $this->isAbsolute && \substr($this->path, -2) === self::DS . '.',
            \is_dir($this->path) => true,
            default => false,
        };
    }

    /**
     * Check if the path is a file.
     * True as the result doesn't mean that the file exists.
     */
    public function isFile(): bool
    {
        return match (true) {
            $this->path === '.',
                $this->path === '..',
                $this->isAbsolute && \substr($this->path, -2) === self::DS . '.' => false,
            \is_file($this->path) => true,
            default => false,
        };
    }

    /**
     * Return a normalized absolute version of this path
     */
    public function absolute(): self
    {
        if ($this->isAbsolute()) {
            return $this;
        }

        $cwd = \getcwd();
        $cwd === false and throw new \RuntimeException('Cannot get current working directory.');
        return self::create($cwd . self::DS . $this->path);
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
     *
     * @param non-empty-string $path A normalized path
     */
    private static function _isAbsolute(string $path): bool
    {
        return \preg_match('~^[a-zA-Z]:~', $path) === 1 || \str_starts_with($path, self::DS);
    }

    /**
     * Normalize a path by converting directory separators and resolving special path segments
     *
     * @return non-empty-string
     */
    private static function normalizePath(string $path): string
    {
        // Normalize directory separators
        $path = \trim(\str_replace(['\\', '/'], self::DS, $path));

        // Normalize multiple separators
        $path = (string) \preg_replace(
            '~' . \preg_quote(self::DS, '~') . '{2,}~',
            self::DS,
            $path,
        );

        // Empty path becomes current directory
        if ($path === '') {
            return '.';
        }


        // Determine if the path is absolute
        $isAbsolute = self::_isAbsolute($path);

        // Resolve special path segments
        $parts = \explode(self::DS, $path);
        /** @var non-empty-string|null $driverLetter */

        if ($isAbsolute && \preg_match('~^([a-zA-Z]):~', $path, $matches) === 1) {
            // Windows-style path with a drive letter
            $driverLetter = $matches[1];
            \array_shift($parts);
        } else {
            $driverLetter = null;
        }

        $result = [];
        foreach ($parts as $part) {
            $part = \trim($part, ' ');
            if ($part === '.' || $part === '') {
                continue;
            }

            if ($part === '..') {
                if ($result !== [] && $result[\array_key_last($result)] !== '..') {
                    \array_pop($result);
                    continue;
                }

                $isAbsolute and throw new \LogicException("Cannot go up from root in `{$path}`");
                $result[] = '..';
                continue;
            }

            $result[] = $part;
        }

        // Reconstruct the path
        $normalizedPath = $result === [] ? '.' : \implode(self::DS, $result);

        // Add an absolute path prefix if necessary
        if ($isAbsolute) {
            $normalizedPath = $driverLetter !== null
                ? "$driverLetter:" . self::DS . $normalizedPath
                : self::DS . $normalizedPath;
        }

        return $normalizedPath;
    }
}
