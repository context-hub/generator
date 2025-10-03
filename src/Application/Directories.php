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
            $this->set(name: $name, path: $directory);
        }
    }

    public function has(string $name): bool
    {
        return \array_key_exists(key: $name, array: $this->directories);
    }

    public function set(string $name, string $path): DirectoriesInterface
    {
        $this->directories[$name] = \rtrim(string: $path, characters: '/') . '/';

        return $this;
    }

    public function get(string $name): string
    {
        if (!$this->has(name: $name)) {
            throw new DirectoryException(message: "Undefined directory '{$name}'");
        }

        return $this->directories[$name];
    }

    public function getAll(): array
    {
        return $this->directories;
    }
}
