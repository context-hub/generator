<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Domain\Model;

/**
 * Entry type definition with validation rules and statuses
 */
final readonly class EntryType
{
    /**
     * @param string $key Unique identifier for this entry type
     * @param string $displayName Human-readable name
     * @param string $contentType MIME type for content
     * @param string $defaultStatus Default status value for new entries
     * @param Status[] $statuses Available statuses for this entry type
     */
    public function __construct(
        public string $key,
        public string $displayName,
        public string $contentType,
        public string $defaultStatus,
        public array $statuses,
    ) {}

    /**
     * Get status by value
     */
    public function getStatus(string $value): ?Status
    {
        foreach ($this->statuses as $status) {
            if ($status->value === $value) {
                return $status;
            }
        }
        return null;
    }

    /**
     * Check if status is valid for this entry type
     */
    public function hasStatus(string $value): bool
    {
        return $this->getStatus($value) !== null;
    }
}
