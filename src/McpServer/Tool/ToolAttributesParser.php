<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\Lib\SchemaMapper\SchemaMapperInterface;
use Butschster\ContextGenerator\McpServer\Attribute\InputClass;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Mcp\Types\ToolAnnotations;
use Mcp\Types\ToolInputSchema;

final readonly class ToolAttributesParser
{
    public function __construct(
        private SchemaMapperInterface $schemaMapper,
    ) {}

    public function parse(string $class): \Mcp\Types\Tool
    {
        $reflection = new \ReflectionClass($class);

        // Check for Tool attribute
        $toolAttributes = $reflection->getAttributes(Tool::class);
        if (!empty($toolAttributes)) {
            $tool = $toolAttributes[0]->newInstance();

            $inputSchemaClass = $reflection->getAttributes(InputClass::class)[0] ?? null;
            if ($inputSchemaClass !== null) {
                return $this->createFromInputClass($inputSchemaClass, $tool);
            }


            // Look for InputSchema attributes
            $inputSchemaAttributes = $reflection->getAttributes(InputSchema::class);
            $inputSchema = [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ];

            foreach ($inputSchemaAttributes as $attribute) {
                $schema = $attribute->newInstance();

                // Add to properties
                $property = [
                    'type' => $schema->type,
                ];

                if ($schema->description !== null) {
                    $property['description'] = $schema->description;
                }

                if ($schema->default !== null) {
                    $property['default'] = $schema->default;
                }

                if (!empty($schema->properties)) {
                    $property['properties'] = $schema->properties;
                }

                if (!empty($schema->items)) {
                    $property['items'] = $schema->items;
                }

                if (!empty($schema->enum)) {
                    $property['enum'] = $schema->enum;
                }

                $inputSchema['properties'][$schema->name] = $property;

                // Add to required list if needed
                if ($schema->required) {
                    $inputSchema['required'][] = $schema->name;
                }
            }

            return new \Mcp\Types\Tool(
                name: $tool->name,
                inputSchema: ToolInputSchema::fromArray($inputSchema),
                description: $tool->description,
                annotations: $tool->title ? new ToolAnnotations(
                    title: $tool->title,
                ) : null,
            );
        }
    }

    private function createFromInputClass(\ReflectionAttribute $inputSchemaClass, Tool $tool): \Mcp\Types\Tool
    {
        $inputSchema = $inputSchemaClass->newInstance();

        return new \Mcp\Types\Tool(
            name: $tool->name,
            inputSchema: ToolInputSchema::fromArray(
                $this->schemaMapper->toJsonSchema($inputSchema->class),
            ),
            description: $tool->description,
            annotations: $tool->title ? new ToolAnnotations(
                title: $tool->title,
            ) : null,
        );
    }
}
