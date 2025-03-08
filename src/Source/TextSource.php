<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

/**
 * Source for plain text content
 */
final class TextSource extends BaseSource
{
    /**
     * @param string $description Human-readable description
     * @param string $content Text content
     */
    public function __construct(
        public readonly string $content,
        string $description = '',
    ) {
        parent::__construct($description);
    }
}
