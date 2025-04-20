<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Config;

/**
 * Base implementation for all source configurations
 */
abstract class AbstractSourceConfig implements SourceConfigInterface
{
    /**
     * Creates a new source configuration
     */
    public function __construct(
        protected string $path,
        protected ?string $pathPrefix = null,
        protected ?array $selectiveDocuments = null,
        protected ?FilterConfig $filter = null,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPathPrefix(): ?string
    {
        return $this->pathPrefix;
    }

    public function getSelectiveDocuments(): ?array
    {
        return $this->selectiveDocuments;
    }

    public function getFilter(): ?FilterConfig
    {
        return $this->filter;
    }

    public function getConfigDirectory(): string
    {
        return \dirname($this->getPath());
    }
}
