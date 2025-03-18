<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source;

use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\SourceParserInterface;

abstract class SourceWithModifiers extends BaseSource
{
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
        if (isset($this->modifiers)) {
            $modifiersApplier = $modifiersApplier->withModifiers($this->modifiers);
        }

        return $parser->parse($this, $modifiersApplier);
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'modifiers' => $this->modifiers ?? [],
        ];
    }
}
