<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

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

    public function parseContent(SourceParserInterface $parser): string
    {
        return $parser->parse($this);
    }
}
