<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\Lib\SchemaMapper\SchemaMapperInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Mcp\Types\ToolAnnotations;
use Mcp\Types\ToolInputSchema;

final readonly class ToolAttributesParser
{
    private const string TOOL_NAME_REGEX = '/^[a-zA-Z0-9_-]+$/';

    public function __construct(
        private SchemaMapperInterface $schemaMapper,
    ) {}

    public function parse(string $class): \Mcp\Types\Tool
    {
        $reflection = new \ReflectionClass($class);

        // Check for Tool attribute
        $toolAttributes = $reflection->getAttributes(Tool::class);

        $tool = $toolAttributes[0]->newInstance();

        $inputSchemaClass = $reflection->getAttributes(InputSchema::class)[0] ?? null;
        if ($inputSchemaClass === null) {
            $schema = new ToolInputSchema();
        } else {
            $inputSchema = $inputSchemaClass->newInstance();
            $schema = ToolInputSchema::fromArray($this->schemaMapper->toJsonSchema($inputSchema->class));
        }

        // Tool name can only contain alphanumeric characters and underscores
        if (!\preg_match(self::TOOL_NAME_REGEX, $tool->name)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Tool name "%s" is invalid. It can only contain alphanumeric characters and underscores.',
                    $tool->name,
                ),
            );
        }

        return new \Mcp\Types\Tool(
            name: $tool->name,
            inputSchema: $schema,
            description: $tool->description,
            annotations: $tool->title ? new ToolAnnotations(
                title: $tool->title,
            ) : null,
        );
    }
}
