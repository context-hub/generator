<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Exception;

/**
 * Exception thrown when entry is not found
 */
final class EntryNotFoundException extends DraflingException
{
    public static function withId(string $projectId, string $entryId): self
    {
        return new self("Entry '{$entryId}' not found in project '{$projectId}'");
    }
}
