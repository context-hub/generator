<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;

interface SourceParserInterface
{
    /**
     * Parse content from a source
     *
     * @param SourceInterface $source Source to parse
     * @param ModifiersApplierInterface $modifiersApplier Optional applier for content modifiers
     * @return string Parsed content
     */
    public function parse(
        SourceInterface $source,
        ModifiersApplierInterface $modifiersApplier,
    ): string;
}
