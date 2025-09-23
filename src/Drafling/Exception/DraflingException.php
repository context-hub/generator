<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Exception;

/**
 * Base exception for Drafling system
 */
class DraflingException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
