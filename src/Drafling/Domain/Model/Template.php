<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\Model;

/**
 * Template definition with categories, entry types, and metadata
 */
final readonly class Template
{
    /**
     * @param string $key Unique template identifier
     * @param string $name Human-readable template name
     * @param string $description Template description
     * @param string[] $tags Template tags for categorization
     * @param Category[] $categories Available categories in this template
     * @param EntryType[] $entryTypes Available entry types in this template
     * @param string|null $prompt Optional prompt for AI assistance
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public array $tags,
        public array $categories,
        public array $entryTypes,
        public ?string $prompt = null,
    ) {}

    /**
     * Get category by name
     */
    public function getCategory(string $name): ?Category
    {
        foreach ($this->categories as $category) {
            if ($category->name === $name) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Get entry type by key
     */
    public function getEntryType(string $key): ?EntryType
    {
        foreach ($this->entryTypes as $entryType) {
            if ($entryType->key === $key) {
                return $entryType;
            }
        }
        return null;
    }

    /**
     * Check if category exists in template
     */
    public function hasCategory(string $name): bool
    {
        return $this->getCategory($name) !== null;
    }

    /**
     * Check if entry type exists in template
     */
    public function hasEntryType(string $key): bool
    {
        return $this->getEntryType($key) !== null;
    }

    /**
     * Validate entry type is allowed in category
     */
    public function validateEntryInCategory(string $categoryName, string $entryTypeKey): bool
    {
        $category = $this->getCategory($categoryName);
        if ($category === null) {
            return false;
        }

        return $category->allowsEntryType($entryTypeKey);
    }
}
