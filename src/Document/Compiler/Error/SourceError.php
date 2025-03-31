<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document\Compiler\Error;

use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Represents an error that occurred with a specific source
 */
final readonly class SourceError implements \Stringable
{
    public function __construct(
        public SourceInterface $source,
        public \Throwable $exception,
    ) {}

    /**
     * Get the source description for error reporting
     */
    public function getSourceDescription(): string
    {
        $source = new \ReflectionClass($this->source);
        return $this->source->hasDescription()
            ? $this->source->getDescription()
            : $source->getShortName();
    }

    /**
     * Get a formatted error message
     */
    public function __toString(): string
    {
        return \sprintf(
            "Error in %s: %s",
            $this->getSourceDescription(),
            $this->exception->getMessage(),
        );
    }
}
