<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceInterface;
use Butschster\ContextGenerator\SourceParserInterface;

abstract class BaseSource implements SourceInterface
{
    /**
     * @param string $description Human-readable description
     */
    public function __construct(
        protected readonly string $description,
        protected readonly array $tags = [],
    ) {}

    public function getDescription(): string
    {
        return \trim($this->description);
    }

    public function hasDescription(): bool
    {
        return $this->getDescription() !== '';
    }

    /**
     * Get all source tags
     *
     * @return array<string>
     */
    public function getTags(): array
    {
        return \array_values(\array_unique($this->tags));
    }

    /**
     * Check if source has any tags
     */
    public function hasTags(): bool
    {
        return !empty($this->getTags());
    }

    /**
     * Parse the content from this source.
     *
     * @param SourceParserInterface $parser The parser to use
     * @param ModifiersApplierInterface $modifiersApplier Optional applier for document-level modifiers
     * @return string The parsed content
     */
    public function parseContent(
        SourceParserInterface $parser,
        ModifiersApplierInterface $modifiersApplier,
    ): string {
        return $parser->parse($this, $modifiersApplier);
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'description' => $this->getDescription(),
            'tags' => $this->getTags(),
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
