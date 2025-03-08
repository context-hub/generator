<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

/**
 * Interface for source parsers
 */
interface SourceParserInterface
{
    public function parse(SourceInterface $source): string;
}
