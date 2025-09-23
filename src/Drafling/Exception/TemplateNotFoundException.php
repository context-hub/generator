<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Exception;

/**
 * Exception thrown when template is not found
 */
final class TemplateNotFoundException extends DraflingException
{
    public static function withKey(string $key): self
    {
        return new self("Template with key '{$key}' not found");
    }
}
