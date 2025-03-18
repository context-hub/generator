<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Schema;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Schema\Attribute\SchemaType;

/**
 * Root schema class representing the entire context generator configuration
 */
#[SchemaType(name: 'contextGenerator', description: 'Context Generator Configuration')]
final readonly class JsonSchema implements \JsonSerializable
{
    /**
     * @param array<Document> $documents List of documents to generate
     * @param Settings|null $settings Global settings
     */
    public function __construct(
        public array $documents = [],
        public ?Settings $settings = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'documents' => \array_values($this->documents),
            'settings' => $this->settings,
        ];
    }
}