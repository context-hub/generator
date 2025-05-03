<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * Registry for file exclusion patterns
 *
 * @implements RegistryInterface<ExclusionPatternInterface>
 */
#[Singleton]
final class ExcludeRegistry implements ExcludeRegistryInterface, RegistryInterface
{
    /** @var array<ExclusionPatternInterface> */
    private array $patterns = [];

    public function __construct(
        #[LoggerPrefix(prefix: 'exclude-registry')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Add a new exclusion pattern
     */
    public function addPattern(ExclusionPatternInterface $pattern): self
    {
        $this->patterns[] = $pattern;

        $this->logger?->debug('Added exclusion pattern', [
            'pattern' => $pattern->getPattern(),
        ]);

        return $this;
    }

    /**
     * Check if a path should be excluded
     */
    public function shouldExclude(string $path): bool
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern->matches($path)) {
                $this->logger?->debug('Path excluded by pattern', [
                    'path' => $path,
                    'pattern' => $pattern->getPattern(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Get all registered exclusion patterns
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * Get the registry type
     */
    public function getType(): string
    {
        return 'exclude';
    }

    /**
     * Get all items in the registry
     */
    public function getItems(): array
    {
        return $this->patterns;
    }

    /**
     * Make the registry iterable
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->patterns);
    }

    /**
     * JSON serialization
     */
    public function jsonSerialize(): array
    {
        return [
            'patterns' => \array_map(
                static fn(ExclusionPatternInterface $pattern) => $pattern->jsonSerialize(),
                $this->patterns,
            ),
        ];
    }
}
