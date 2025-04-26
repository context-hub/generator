<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document\Compiler\Error;

/**
 * Collection of source errors that occurred during document compilation
 * @template TError of \Stringable|string
 * @implements \IteratorAggregate<TError>
 */
final class ErrorCollection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    public function __construct(
        /**
         * @var array<TError>
         */
        private array $errors = [],
    ) {}

    /**
     * Add a source error to the collection
     * @param TError $error The error message
     */
    public function add(\Stringable|string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Check if the collection has any errors
     */
    public function hasErrors(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Get the number of errors in the collection
     */
    public function count(): int
    {
        return \count($this->errors);
    }

    /**
     * Make the collection iterable
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->errors);
    }

    public function jsonSerialize(): array
    {
        return \array_map(
            static fn(\Stringable|string $error): string => (string) $error,
            $this->errors,
        );
    }
}
