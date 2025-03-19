<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer\Package;

/**
 * Collection of ComposerPackageInfo objects with helpful methods
 * @implements \IteratorAggregate<string, ComposerPackageInfo>
 * @implements \ArrayAccess<string, ComposerPackageInfo>
 */
final class ComposerPackageCollection implements \IteratorAggregate, \ArrayAccess, \Countable, \JsonSerializable
{
    /**
     * @var array<string, ComposerPackageInfo> Package name => Package info
     */
    private array $packages = [];

    /**
     * @param array<string, ComposerPackageInfo> $packages
     */
    public function __construct(array $packages = [])
    {
        foreach ($packages as $package) {
            $this->add($package);
        }
    }

    /**
     * Add a package to the collection
     */
    public function add(ComposerPackageInfo $package): void
    {
        $this->packages[$package->name] = $package;
    }

    /**
     * Get a package by name
     */
    public function get(string $name): ?ComposerPackageInfo
    {
        return $this->packages[$name] ?? null;
    }

    /**
     * Check if a package exists in the collection
     */
    public function has(string $name): bool
    {
        return isset($this->packages[$name]);
    }

    /**
     * Remove a package from the collection
     */
    public function remove(string $name): void
    {
        unset($this->packages[$name]);
    }

    /**
     * Filter packages based on pattern(s)
     *
     * @param string|array<string> $pattern Pattern(s) to match package names
     * @return self New collection with filtered packages
     */
    public function filter(string|array $pattern): self
    {
        // If no pattern is provided, return all packages
        if (empty($pattern)) {
            return clone $this;
        }

        $patterns = (array) $pattern;
        $filtered = [];

        foreach ($this->packages as $name => $package) {
            foreach ($patterns as $pattern) {
                if (\fnmatch($pattern, $name)) {
                    $filtered[$name] = $package;
                    break;
                }
            }
        }

        return new self($filtered);
    }

    /**
     * Get all packages as an array
     *
     * @return array<string, ComposerPackageInfo>
     */
    public function all(): array
    {
        return $this->packages;
    }

    /**
     * Get iterator for the collection
     *
     * @return \Traversable<string, ComposerPackageInfo>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->packages);
    }

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->packages[$offset]);
    }

    /**
     * @param string $offset
     */
    public function offsetGet(mixed $offset): ?ComposerPackageInfo
    {
        return $this->packages[$offset] ?? null;
    }

    /**
     * @param string $offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof ComposerPackageInfo) {
            throw new \InvalidArgumentException(
                \sprintf('Value must be an instance of %s', ComposerPackageInfo::class),
            );
        }

        $this->packages[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->packages[$offset]);
    }

    /**
     * Get the number of packages in the collection
     */
    public function count(): int
    {
        return \count($this->packages);
    }

    /**
     * Generate a tree view of packages
     */
    public function generateTree(): string
    {
        if (empty($this->packages)) {
            return "No composer packages found.\n";
        }

        $tree = "Composer packages:\n";

        foreach ($this->packages as $name => $package) {
            $tree .= "├── {$name} ({$package->version})\n";

            // Add description if available
            if ($description = $package->getDescription()) {
                // Truncate long descriptions
                if (\strlen($description) > 60) {
                    $description = \substr($description, 0, 57) . '...';
                }
                $tree .= "│   └── {$description}\n";
            }

            // Add source directories if available
            $sourceDirs = $package->getSourceDirectories();
            $lastSourceDirIndex = \count($sourceDirs) - 1;

            foreach ($sourceDirs as $index => $dir) {
                $isLast = $index === $lastSourceDirIndex;
                $prefix = $isLast ? '│   └── ' : '│   ├── ';
                $tree .= $prefix . $dir . "\n";
            }
        }

        return $tree;
    }

    public function jsonSerialize(): array
    {
        return \array_map(static fn($package) => [
            'version' => $package->version,
            'description' => $package->getDescription(),
            'path' => $package->path,
        ], $this->packages);
    }
}
