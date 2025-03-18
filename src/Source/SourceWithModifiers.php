<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceParserInterface;

abstract class SourceWithModifiers extends BaseSource
{
    /**
     * @param array<non-empty-string> $tags
     * @param array<Modifier> $modifiers Identifiers for content modifiers to apply
     */
    public function __construct(
        string $description,
        array $tags = [],
        private readonly array $modifiers = [],
    ) {
        parent::__construct(description: $description, tags: $tags);
    }

    public function parseContent(
        SourceParserInterface $parser,
        ModifiersApplierInterface $modifiersApplier,
    ): string {
        // If we have source-specific modifiers and a document-level modifiers applier,
        // create a new applier that includes both sets of modifiers
        $modifiersApplier = $modifiersApplier->withModifiers($this->modifiers);

        return $parser->parse($this, $modifiersApplier);
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'modifiers' => $this->modifiers,
        ];
    }
}
