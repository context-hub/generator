<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\Model;

/**
 * Template category definition
 */
final readonly class Category
{
    /**
     * @param string $name Category identifier
     * @param string $displayName Human-readable name
     * @param string[] $entryTypes Array of entry type keys allowed in this category
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public array $entryTypes,
    ) {}

    /**
     * Check if entry type is allowed in this category
     */
    public function allowsEntryType(string $entryType): bool
    {
        return \in_array($entryType, $this->entryTypes, true);
    }
}
