<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

final class Document
{
    /** @var array<SourceInterface> */
    private array $sources = [];

    public static function create(
        string $description,
        string $outputPath,
    ): self {
        return new self(
            description: $description,
            outputPath: $outputPath,
        );
    }

    /**
     * @param string $description Human-readable description
     * @param string $outputPath Path where to write the output
     */
    public function __construct(
        public readonly string $description,
        public readonly string $outputPath,
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
