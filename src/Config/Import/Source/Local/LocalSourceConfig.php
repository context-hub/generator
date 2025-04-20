<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Local;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Config\Import\PathMatcher;
use Butschster\ContextGenerator\Config\Import\Source\Config\AbstractSourceConfig;
use Butschster\ContextGenerator\Config\Import\Source\Config\FilterConfig;

/**
 * Configuration for local filesystem imports
 */
final class LocalSourceConfig extends AbstractSourceConfig
{
    /**
     * Creates a new local source configuration
     */
    public function __construct(
        string $path,
        private readonly string $absolutePath,
        private readonly bool $hasWildcard = false,
        ?string $pathPrefix = null,
        ?array $selectiveDocuments = null,
        ?FilterConfig $filter = null,
    ) {
        parent::__construct($path, $pathPrefix, $selectiveDocuments, $filter);
    }

    /**
     * Create from an array configuration
     *
     * @param array $config Import configuration array
     * @param string $basePath Base path for resolving relative paths
     */
    public static function fromArray(array $config, string $basePath): self
    {
        if (!isset($config['path'])) {
            throw new \InvalidArgumentException("Source configuration must have a 'path' property");
        }

        $path = $config['path'];
        $pathPrefix = $config['pathPrefix'] ?? null;
        $selectiveDocuments = $config['docs'] ?? null;
        $filter = FilterConfig::fromArray($config['filter'] ?? null);

        // Check if the path contains wildcards
        $hasWildcard = PathMatcher::containsWildcard($path);

        // Resolve relative path to absolute path
        $absolutePath = (string) FSPath::create($basePath)->join($path);

        return new self(
            path: $path,
            absolutePath: $absolutePath,
            hasWildcard: $hasWildcard,
            pathPrefix: $pathPrefix,
            selectiveDocuments: $selectiveDocuments,
            filter: $filter,
        );
    }

    public function getType(): string
    {
        return 'local';
    }

    /**
     * Get the absolute path to the local file
     */
    public function getAbsolutePath(): string
    {
        return $this->absolutePath;
    }

    /**
     * Check if the path contains wildcard characters
     */
    public function hasWildcard(): bool
    {
        return $this->hasWildcard;
    }

    #[\Override]
    public function getConfigDirectory(): string
    {
        return \dirname($this->absolutePath);
    }

    public function jsonSerialize(): array
    {
        $result = [
            'type' => $this->getType(),
            'path' => $this->path,
            'pathPrefix' => $this->pathPrefix,
        ];

        if ($this->filter !== null && !$this->filter->isEmpty()) {
            $result['filter'] = $this->filter;
        }

        return $result;
    }
}
