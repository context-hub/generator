<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\Model;

/**
 * Template definition with categories, entry types, and metadata
 */
final readonly class Template implements \JsonSerializable
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

    public function jsonSerialize(): array
    {
        $formatted = [
            'template_id' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'tags' => $this->tags,
        ];

        $formatted['categories'] = \array_map(static fn($category) => [
            'name' => $category->name,
            'display_name' => $category->displayName,
            'allowed_entry_types' => $category->entryTypes,
        ], $this->categories);

        $formatted['entry_types'] = \array_map(static fn($entryType) => [
            'key' => $entryType->key,
            'display_name' => $entryType->displayName,
            'default_status' => $entryType->defaultStatus,
            'statuses' => \array_map(static fn($status) => $status->value, $entryType->statuses),
        ], $this->entryTypes);

        if ($this->prompt !== null) {
            $formatted['prompt'] = $this->prompt;
        }

        return $formatted;
    }
}
