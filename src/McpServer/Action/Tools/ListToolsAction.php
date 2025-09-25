<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\ToolProviderInterface;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use PhpMcp\Schema\Result\ListToolsResult;
use PhpMcp\Schema\Tool;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListToolsAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ReferenceProviderInterface $provider,
        private ToolProviderInterface $toolProvider,
    ) {}

    #[Get(path: '/tools/list', name: 'tools.list')]
    public function __invoke(ServerRequestInterface $request): ListToolsResult
    {
        $this->logger->info('Listing available tools');

        $tools = \array_values($this->provider->getTools());
        foreach ($this->toolProvider->all() as $toolDefinition) {
            $tools[] = new Tool(
                name: $toolDefinition->id,
                inputSchema: $this->buildInputSchema($toolDefinition),
                description: $toolDefinition->description,
                annotations: null,
            );
        }

        return new ListToolsResult($tools);
    }

    /**
     * Build a ToolInputSchema object from the tool definition's schema.
     */
    private function buildInputSchema(ToolDefinition $toolDefinition): array
    {
        // If no schema is defined, return an empty schema
        if ($toolDefinition->schema === null) {
            return [
                'type' => 'object',
                'properties' => new \stdClass(),
            ];
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
        return $schemaData;
    }
}
