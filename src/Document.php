<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

final class Document
{
    /** @var array<SourceInterface> */
    private array $sources = [];

    /**
     * @param bool $overwrite Whether to overwrite the file if it already exists
     */
    public static function create(
        string $description,
        string $outputPath,
        bool $overwrite = true,
    ): self {
        return new self(
            description: $description,
            outputPath: $outputPath,
            overwrite: $overwrite,
        );
    }

    /**
     * @param string $description Human-readable description
     * @param string $outputPath Path where to write the output
     */
    public function __construct(
        public readonly string $description,
        public readonly string $outputPath,
        public readonly bool $overwrite = true,
        SourceInterface ...$sources,
    ) {
        $this->sources = $sources;
    }

    /**
     * Add a source to this document
     */
    public function addSource(SourceInterface ...$sources): self
    {
        $this->sources = [...$this->sources, ...$sources];

        return $this;
    }

    /**
     * Get all sources
     *
     * @return array<SourceInterface>
     */
    public function getSources(): array
    {
        return $this->sources;
    }
}
