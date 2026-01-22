<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

/**
 * Configuration for RAG-type tools defined in context.yaml.
 *
 * Represents a tool that provides search and/or store operations
 * for a specific RAG collection.
 */
final readonly class RagToolConfig
{
    public const string OPERATION_SEARCH = 'search';
    public const string OPERATION_STORE = 'store';

    /**
     * @param string $id Tool identifier
     * @param string|null $name Optional display name (uses id if not provided)
     * @param string $description Tool description for AI
     * @param string $collection Reference to named collection
     * @param string[] $operations Available operations (search, store)
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public string $description,
        public string $collection,
        public array $operations = [self::OPERATION_SEARCH, self::OPERATION_STORE],
    ) {
        $this->validateOperations($operations);
    }

    public static function fromArray(array $data): self
    {
        if (empty($data['id']) || !\is_string($data['id'])) {
            throw new \InvalidArgumentException('RAG tool must have a non-empty id');
        }

        if (empty($data['description']) || !\is_string($data['description'])) {
            throw new \InvalidArgumentException('RAG tool must have a non-empty description');
        }

        if (empty($data['collection']) || !\is_string($data['collection'])) {
            throw new \InvalidArgumentException('RAG tool must specify a collection');
        }

        $operations = $data['operations'] ?? [self::OPERATION_SEARCH, self::OPERATION_STORE];
        if (!\is_array($operations)) {
            throw new \InvalidArgumentException('RAG tool operations must be an array');
        }

        return new self(
            id: $data['id'],
            name: isset($data['name']) && \is_string($data['name']) ? $data['name'] : null,
            description: $data['description'],
            collection: $data['collection'],
            operations: \array_values($operations),
        );
    }

    /**
     * Get the tool name (uses name if provided, otherwise id).
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function hasSearch(): bool
    {
        return \in_array(self::OPERATION_SEARCH, $this->operations, true);
    }

    public function hasStore(): bool
    {
        return \in_array(self::OPERATION_STORE, $this->operations, true);
    }

    /**
     * Get the tool ID for search operation.
     */
    public function getSearchToolId(): string
    {
        return $this->id;
    }

    /**
     * Get the tool ID for store operation.
     */
    public function getStoreToolId(): string
    {
        return $this->id . '-store';
    }

    private function validateOperations(array $operations): void
    {
        $valid = [self::OPERATION_SEARCH, self::OPERATION_STORE];

        foreach ($operations as $op) {
            if (!\in_array($op, $valid, true)) {
                throw new \InvalidArgumentException(
                    \sprintf('Invalid RAG operation "%s". Valid: %s', $op, \implode(', ', $valid)),
                );
            }
        }

        if (empty($operations)) {
            throw new \InvalidArgumentException('RAG tool must have at least one operation');
        }
    }
}
