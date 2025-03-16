<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\SourceInterface;

final class Document implements \JsonSerializable
{
    /** @var array<SourceInterface> */
    private array $sources = [];

    /** @var array<Modifier> */
    private array $modifiers = [];

    /**
     * @param string $description Human-readable description
     * @param string $outputPath Path where to write the output
     * @param bool $overwrite Whether to overwrite the file if it already exists
     * @param array<Modifier> $modifiers Modifiers to apply to all sources
     */
    public function __construct(
        public readonly string $description,
        public readonly string $outputPath,
        public readonly bool $overwrite = true,
        array $modifiers = [],
        SourceInterface ...$sources,
    ) {
        $this->sources = $sources;
        $this->modifiers = $modifiers;
    }

    /**
     * @param bool $overwrite Whether to overwrite the file if it already exists
     */
    public static function create(
        string $description,
        string $outputPath,
        bool $overwrite = true,
        array $modifiers = [],
    ): self {
        return new self(
            description: $description,
            outputPath: $outputPath,
            overwrite: $overwrite,
            modifiers: $modifiers,
        );
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
     * Add modifiers to this document
     */
    public function addModifier(Modifier ...$modifiers): self
    {
        $this->modifiers = [...$this->modifiers, ...$modifiers];

        return $this;
    }

    /**
     * Get all sources
     *
     * @return array<int, SourceInterface>
     */
    public function getSources(): array
    {
        return \array_values($this->sources);
    }

    /**
     * Get all document modifiers
     *
     * @return array<Modifier>
     */
    public function getModifiers(): array
    {
        return \array_values($this->modifiers);
    }

    /**
     * Check if document has modifiers
     */
    public function hasModifiers(): bool
    {
        return !empty($this->modifiers);
    }

    /**
     * @return (SourceInterface[]|string|true)[]
     *
     * @psalm-return array{description?: string, outputPath?: string, overwrite?: true, sources?: array<SourceInterface>}
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'description' => $this->description,
            'outputPath' => $this->outputPath,
            'overwrite' => $this->overwrite,
            'sources' => $this->getSources(),
            'modifiers' => $this->modifiers,
        ], static fn($value) => $value !== null && $value !== []);
    }
}
