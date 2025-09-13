<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Registry;

use Butschster\ContextGenerator\McpServer\Attribute\Prompt;
use Butschster\ContextGenerator\McpServer\Attribute\Resource;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Tool\ToolAttributesParser;
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
        private readonly ToolAttributesParser $toolAttributesParser,
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
            $this->tools[$tool->name] = $this->toolAttributesParser->parse($className);

            $this->logger->info('Registered tool', [
                'name' => $tool->name,
                'description' => $tool->description,
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
     * @return array<\Mcp\Types\Tool>
     */
    public function getTools(): array
    {
        return \array_values($this->tools);
    }
}
