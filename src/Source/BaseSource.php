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
}
