<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
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
        return \trim(string: $this->description);
    }

    public function hasDescription(): bool
    {
        return $this->getDescription() !== '';
    }

    public function getTags(): array
    {
        return \array_values(array: \array_unique(array: $this->tags));
    }

    public function hasTags(): bool
    {
        return !empty($this->getTags());
    }

    public function parseContent(
        SourceParserInterface $parser,
        ModifiersApplierInterface $modifiersApplier,
    ): string {
        return $parser->parse($this, $modifiersApplier);
    }

    public function jsonSerialize(): array
    {
        return \array_filter(array: [
            'description' => $this->getDescription(),
            'tags' => $this->getTags(),
        ], callback: static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
