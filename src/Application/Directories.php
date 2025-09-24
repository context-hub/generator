<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\Exception\DirectoryException;

final class Directories implements DirectoriesInterface
{
    /**
     * @param array<non-empty-string, string> $directories
     */
    public function __construct(
        private array $directories = [],
    ) {
        foreach ($directories as $name => $directory) {
            $this->set($name, $directory);
        }
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->directories);
    }

    public function set(string $name, string $path): DirectoriesInterface
    {
        $this->directories[$name] = \rtrim($path, '/') . '/';

        return $this;
    }

    public function get(string $name): string
    {
        if (!$this->has($name)) {
            throw new DirectoryException("Undefined directory '{$name}'");
        }

        return $this->directories[$name];
    }

    public function getAll(): array
    {
        return $this->directories;
    }
}
