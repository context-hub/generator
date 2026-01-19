<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema;

/**
 * Holds schema-level metadata for the JSON Schema output.
 */
final class SchemaMetadata
{
    /**
     * @param string $schema JSON Schema draft version
     * @param string $id Schema identifier URL
     * @param string $title Human-readable title
     * @param string $description Schema description
     * @param array<string> $fileMatch Files this schema applies to
     */
    public function __construct(
        public readonly string $schema = 'http://json-schema.org/draft-07/schema#',
        public readonly string $id = 'https://ctxllm.com',
        public readonly string $title = 'ContextHub Configuration',
        public readonly string $description = 'Configuration schema for ContextHub document generator',
        public readonly array $fileMatch = ['context.json', 'context.yaml'],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            '$schema' => $this->schema,
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'fileMatch' => $this->fileMatch,
        ];
    }
}
