<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Config\Registry\RegistryInterface;

/**
 * @implements RegistryInterface<Document>
 */
final class DocumentRegistry implements RegistryInterface, \ArrayAccess
{
    public function __construct(
        /** @var array<Document> */
        private array $documents = [],
    ) {}

    public function getType(): string
    {
        return 'documents';
    }

    /**
     * Register a document in the registry
     */
    public function register(Document $document): self
    {
        $this->documents[] = $document;

        return $this;
    }

    public function getItems(): array
    {
        return $this->documents;
    }

    public function jsonSerialize(): array
    {
        return $this->documents;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getItems());
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->documents);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->documents[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Cannot set value directly. Use register() method.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Cannot unset value directly.');
    }
}
