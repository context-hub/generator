<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Exception;

/**
 * Exception thrown when project is not found
 */
final class ProjectNotFoundException extends DraflingException
{
    public static function withId(string $id): self
    {
        return new self("Project with ID '{$id}' not found");
    }
}
