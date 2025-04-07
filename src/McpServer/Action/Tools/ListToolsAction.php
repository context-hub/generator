<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\ToolProviderInterface;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputSchema;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListToolsAction
{
    public function __construct(
        private LoggerInterface $logger,
        private McpItemsRegistry $registry,
        private ToolProviderInterface $toolProvider,
    ) {}

    #[Get(path: '/tools/list', name: 'tools.list')]
    public function __invoke(ServerRequestInterface $request): ListToolsResult
    {
        $this->logger->info('Listing available tools');

        $tools = $this->registry->getTools();
        foreach ($this->toolProvider->all() as $toolDefinition) {
            // Create the input schema object based on the tool's schema
            $inputSchema = $this->buildInputSchema($toolDefinition);

            $tools[] = new Tool(
                name: $toolDefinition->id,
                inputSchema: $inputSchema,
                description: $toolDefinition->description,
            );
        }

        return new ListToolsResult($tools);
    }

    /**
     * Build a ToolInputSchema object from the tool definition's schema.
     */
    private function buildInputSchema(ToolDefinition $toolDefinition): ToolInputSchema
    {
        // If no schema is defined, return an empty schema
        if ($toolDefinition->schema === null) {
            return new ToolInputSchema();
        }

        // Convert the tool's schema to array format expected by ToolInputSchema
        $schemaData = [
            'type' => 'object',
            'properties' => $toolDefinition->schema->getProperties(),
        ];

        $required = $toolDefinition->schema->getRequiredProperties();
        if (!empty($required)) {
            $schemaData['required'] = $required;
        }

        // Use the fromArray method to create the ToolInputSchema
        return ToolInputSchema::fromArray($schemaData);
    }
}