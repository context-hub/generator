<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceParserInterface;

interface SourceInterface extends \JsonSerializable
{
    /**
     * Get source description
     */
    public function getDescription(): string;

    public function hasDescription(): bool;

    /**
     * Get all source tags
     *
     * @return array<non-empty-string>
     */
    public function getTags(): array;

    /**
     * Check if source has any tags
     */
    public function hasTags(): bool;

    /**
     * Parse the content for this source
     *
     * @param SourceParserInterface $parser Parser for the source content
     * @param ModifiersApplierInterface $modifiersApplier Applier for content modifiers
     * @return string Parsed content
     */
    public function parseContent(
        SourceParserInterface $parser,
        ModifiersApplierInterface $modifiersApplier,
    ): string;
}
