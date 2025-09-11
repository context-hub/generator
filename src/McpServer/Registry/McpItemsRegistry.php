<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Registry;

use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Prompt;
use Butschster\ContextGenerator\McpServer\Attribute\Resource;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Mcp\Types\ToolAnnotations;
use Mcp\Types\ToolInputSchema;
use Psr\Log\LoggerInterface;

final class McpItemsRegistry
{
    /** @var array<\Mcp\Types\Prompt> */
    private array $prompts = [];

    /** @var array<\Mcp\Types\Resource> */
    private array $resources = [];

    /** @var array<\Mcp\Types\Tool> */
    private array $tools = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Register a class and scan for MCP item attributes
     */
    public function register(string $className): void
    {
        $reflection = new \ReflectionClass($className);

        // Check for Prompt attribute
        $promptAttributes = $reflection->getAttributes(Prompt::class);
        if (!empty($promptAttributes)) {
            $prompt = $promptAttributes[0]->newInstance();
            $this->prompts[$prompt->name] = new \Mcp\Types\Prompt(
                name: $prompt->name,
                description: $prompt->description,
            );

            $this->logger->info('Registered prompt', [
                'name' => $prompt->name,
                'description' => $prompt->description,
            ]);
        }

        // Check for Resource attribute
        $resourceAttributes = $reflection->getAttributes(Resource::class);
        if (!empty($resourceAttributes)) {
            $resource = $resourceAttributes[0]->newInstance();
            $this->resources[$resource->name] = new \Mcp\Types\Resource(
                name: $resource->name,
                uri: $resource->uri,
                description: $resource->description,
                mimeType: $resource->mimeType,
            );

            $this->logger->info('Registered resource', [
                'name' => $resource->name,
                'uri' => $resource->uri,
                'description' => $resource->description,
                'mimeType' => $resource->mimeType,
            ]);
        }

        // Check for Tool attribute
        $toolAttributes = $reflection->getAttributes(Tool::class);
        if (!empty($toolAttributes)) {
            $tool = $toolAttributes[0]->newInstance();

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

            $this->tools[$tool->name] = new \Mcp\Types\Tool(
                name: $tool->name,
                inputSchema: ToolInputSchema::fromArray($inputSchema),
                description: $tool->description,
                annotations: $tool->title ? new ToolAnnotations(
                    title: $tool->title,
                ) : null,
            );

            $this->logger->info('Registered tool', [
                'name' => $tool->name,
                'description' => $tool->description,
                'inputSchema' => $inputSchema,
            ]);
        }
    }

    /**
     * Register multiple classes at once
     */
    public function registerMany(array $classNames): void
    {
        foreach ($classNames as $className) {
            $this->register($className);
        }
    }

    /**
     * Get all registered prompts
     */
    public function getPrompts(): array
    {
        return \array_values($this->prompts);
    }

    /**
     * Get all registered resources
     */
    public function getResources(): array
    {
        return \array_values($this->resources);
    }

    /**
     * Get all registered tools
     */
    public function getTools(): array
    {
        return \array_values($this->tools);
    }
}
