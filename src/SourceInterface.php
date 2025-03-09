<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

interface SourceInterface extends \JsonSerializable
{
    /**
     * Get source description
     */
    public function getDescription(): string;

    public function hasDescription(): bool;

    /**
     * Parse source content using the appropriate parser
     */
    public function parseContent(SourceParserInterface $parser): string;
}
