<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\SourceInterface;

final class Document implements \JsonSerializable
{
    /** @var array<SourceInterface> */
    private array $sources = [];

    /** @var array<string> */
    private array $errors = [];

    /**
     * @param string $description Human-readable description
     * @param string $outputPath Path where to write the output
     * @param bool $overwrite Whether to overwrite the file if it already exists
     * @param array<Modifier> $modifiers Modifiers to apply to all sources
     * @param array<non-empty-string> $tags Tags to apply to the document
     */
    public function __construct(
        public readonly string $description,
        public readonly string $outputPath,
        public readonly bool $overwrite = true,
        private array $modifiers = [],
        private array $tags = [],
        SourceInterface ...$sources,
    ) {
        $this->sources = $sources;
    }

    /**
     * @param bool $overwrite Whether to overwrite the file if it already exists
     * @param array<non-empty-string> $tags Tags to apply to the document
     */
    public static function create(
        string $description,
        string $outputPath,
        bool $overwrite = true,
        array $modifiers = [],
        array $tags = [],
    ): self {
        return new self(
            description: $description,
            outputPath: $outputPath,
            overwrite: $overwrite,
            modifiers: $modifiers,
            tags: $tags,
        );
    }

    /**
     * Get all document errors
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return \array_values($this->errors);
    }

    public function addError(string $error): self
    {
        $this->errors[] = $error;

        return $this;
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
     * Add tags to this document
     */
    public function addTag(string ...$tags): self
    {
        $this->tags = [...$this->tags, ...$tags];

        return $this;
    }

    /**
     * Get all document tags
     *
     * @return array<string>
     */
    public function getTags(bool $includeSources = true): array
    {
        if ($includeSources) {
            foreach ($this->sources as $source) {
                $this->tags = [...$this->tags, ...$source->getTags()];
            }
        }

        return \array_values(\array_unique($this->tags));
    }

    /**
     * Check if document has tags
     */
    public function hasTags(): bool
    {
        return !empty($this->getTags());
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

    public function jsonSerialize(): array
    {
        return \array_filter([
            'description' => $this->description,
            'outputPath' => $this->outputPath,
            'overwrite' => $this->overwrite,
            'sources' => $this->getSources(),
            'modifiers' => $this->getModifiers(),
            'tags' => $this->getTags(),
        ], static fn($value) => $value !== null && $value !== []);
    }
}
