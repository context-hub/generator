# Stage 5: Dynamic Tool Generation

## Overview

Create dynamic MCP tool actions that are configured at runtime based on RAG tool definitions from `context.yaml`. The
`RagToolFactory` creates action instances for each tool, and the `ActionsBootloader` registers them alongside static
tools.

## Files

**CREATE:**

- `rag/Tool/DynamicRagSearchAction.php` - Configurable search action
- `rag/Tool/DynamicRagStoreAction.php` - Configurable store action
- `rag/Tool/RagToolFactory.php` - Factory to build actions from config
- `rag/Tool/RagToolRegistryInterface.php` - Interface for RAG tool registry
- `rag/Tool/RagToolRegistry.php` - Registry for RAG tool configurations
- `tests/src/Rag/Tool/RagToolFactoryTest.php` - Unit tests

**MODIFY:**

- `rag/RagParserPlugin.php` - Parse RAG tools and register in RagToolRegistry
- `rag/RagBootloader.php` - Register RagToolFactory and RagToolRegistry
- `src/McpServer/ActionsBootloader.php` - Register dynamic RAG tools

## Code References

### Static RAG Action Pattern

```php
// rag/MCP/Tools/RagSearch/RagSearchAction.php:15-21
#[Tool(
    name: 'rag-search',
    description: 'Search the project knowledge base...',
    title: 'RAG Search',
)]
#[InputSchema(class: RagSearchRequest::class)]
final readonly class RagSearchAction
```

### ActionsBootloader Tool Registration

```php
// src/McpServer/ActionsBootloader.php:251-256
// RAG Tools - only if enabled in context.yaml
if ($ragRegistry->isEnabled()) {
    $actions[] = RagStoreAction::class;
    $actions[] = RagSearchAction::class;
    $actions[] = RagManageAction::class;
}
```

### Tool Handler Factory Pattern

```php
// src/Tool/ToolHandlerFactory.php (ctx-mcp-server)
public function createHandlerForTool(ToolDefinition $tool): ToolHandlerInterface
{
    return match ($tool->type) {
        'run' => $this->container->get(RunToolHandler::class),
        'http' => $this->container->get(HttpToolHandler::class),
        default => throw new \InvalidArgumentException(/* ... */),
    };
}
```

## Implementation Details

### 1. RagToolRegistryInterface

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

interface RagToolRegistryInterface
{
    /**
     * Register a RAG tool configuration.
     */
    public function register(RagToolConfig $tool): void;

    /**
     * Get all registered RAG tool configurations.
     *
     * @return RagToolConfig[]
     */
    public function all(): array;

    /**
     * Check if any RAG tools are registered.
     */
    public function hasTools(): bool;
}
```

### 2. RagToolRegistry

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class RagToolRegistry implements RagToolRegistryInterface
{
    /** @var RagToolConfig[] */
    private array $tools = [];

    public function register(RagToolConfig $tool): void
    {
        $this->tools[$tool->id] = $tool;
    }

    public function all(): array
    {
        return \array_values($this->tools);
    }

    public function hasTools(): bool
    {
        return !empty($this->tools);
    }
}
```

### 3. DynamicRagSearchAction

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\RagSearchHandler;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * Dynamic RAG search action configured from context.yaml
 */
final readonly class DynamicRagSearchAction
{
    public function __construct(
        private RagToolConfig $config,
        private ServiceFactory $serviceFactory,
        private LoggerInterface $logger,
    ) {}

    public function getToolName(): string
    {
        return $this->config->id;
    }

    public function getToolDescription(): string
    {
        return $this->config->description;
    }

    public function getToolTitle(): string
    {
        return \ucfirst(\str_replace('-', ' ', $this->config->id));
    }

    public function __invoke(RagSearchRequest $request): CallToolResult
    {
        $this->logger->info('Dynamic RAG Search', [
            'tool' => $this->config->id,
            'collection' => $this->config->collection,
            'query' => $request->query,
        ]);

        try {
            $handler = new RagSearchHandler($this->serviceFactory, $this->config->collection);
            return ToolResult::text($handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('Dynamic RAG Search error', [
                'tool' => $this->config->id,
                'error' => $e->getMessage(),
            ]);
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Get JSON schema for this tool's input.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query in natural language',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Filter by type: architecture, api, testing, convention, tutorial, reference, general',
                ],
                'sourcePath' => [
                    'type' => 'string',
                    'description' => 'Filter by source path (exact or prefix match)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of results to return',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
            'required' => ['query'],
        ];
    }
}
```

### 4. DynamicRagStoreAction

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\RagStoreHandler;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;

/**
 * Dynamic RAG store action configured from context.yaml
 */
final readonly class DynamicRagStoreAction
{
    public function __construct(
        private RagToolConfig $config,
        private ServiceFactory $serviceFactory,
        private LoggerInterface $logger,
    ) {}

    public function getToolName(): string
    {
        return $this->config->id;
    }

    public function getToolDescription(): string
    {
        return $this->config->description;
    }

    public function getToolTitle(): string
    {
        return \ucfirst(\str_replace('-', ' ', $this->config->id));
    }

    public function __invoke(RagStoreRequest $request): CallToolResult
    {
        $this->logger->info('Dynamic RAG Store', [
            'tool' => $this->config->id,
            'collection' => $this->config->collection,
            'type' => $request->type,
        ]);

        try {
            $handler = new RagStoreHandler($this->serviceFactory, $this->config->collection);
            return ToolResult::text($handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('Dynamic RAG Store error', [
                'tool' => $this->config->id,
                'error' => $e->getMessage(),
            ]);
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Get JSON schema for this tool's input.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => [
                    'type' => 'string',
                    'description' => 'Content to store in the knowledge base',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Type: architecture, api, testing, convention, tutorial, reference, general',
                    'default' => 'general',
                ],
                'sourcePath' => [
                    'type' => 'string',
                    'description' => 'Source path (e.g., "src/Auth/Service.php")',
                ],
                'tags' => [
                    'type' => 'string',
                    'description' => 'Tags (comma-separated)',
                ],
            ],
            'required' => ['content'],
        ];
    }
}
```

### 5. RagToolFactory

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating dynamic RAG tool actions from configuration.
 */
final readonly class RagToolFactory
{
    public function __construct(
        private RagRegistryInterface $ragRegistry,
        private RagToolRegistryInterface $toolRegistry,
        private ServiceFactory $serviceFactory,
        private LoggerInterface $logger,
    ) {}

    /**
     * Create all dynamic tool actions from registered RAG tools.
     *
     * @return array{search: DynamicRagSearchAction[], store: DynamicRagStoreAction[]}
     */
    public function createAll(): array
    {
        $searchActions = [];
        $storeActions = [];

        foreach ($this->toolRegistry->all() as $toolConfig) {
            // Validate collection exists
            if (!$this->ragRegistry->getConfig()->hasCollection($toolConfig->collection)) {
                $this->logger->warning('RAG tool references non-existent collection', [
                    'tool' => $toolConfig->id,
                    'collection' => $toolConfig->collection,
                ]);
                continue;
            }

            if ($toolConfig->hasSearch()) {
                $searchActions[] = $this->createSearchAction($toolConfig);
            }

            if ($toolConfig->hasStore()) {
                $storeActions[] = $this->createStoreAction($toolConfig);
            }
        }

        return [
            'search' => $searchActions,
            'store' => $storeActions,
        ];
    }

    public function createSearchAction(RagToolConfig $config): DynamicRagSearchAction
    {
        return new DynamicRagSearchAction(
            config: $config,
            serviceFactory: $this->serviceFactory,
            logger: $this->logger,
        );
    }

    public function createStoreAction(RagToolConfig $config): DynamicRagStoreAction
    {
        return new DynamicRagStoreAction(
            config: $config,
            serviceFactory: $this->serviceFactory,
            logger: $this->logger,
        );
    }
}
```

### 6. Updated RagParserPlugin

Add RAG tool parsing to handle tools defined alongside RAG configuration:

```php
// In RagParserPlugin::parse(), after setting RAG config

// Check if there are RAG-type tools in the tools section
if (isset($config['tools']) && \is_array($config['tools'])) {
    foreach ($config['tools'] as $toolConfig) {
        if (($toolConfig['type'] ?? '') === 'rag') {
            try {
                $ragTool = RagToolConfig::fromArray($toolConfig);
                
                // Validate collection exists
                if (!$ragConfig->hasCollection($ragTool->collection)) {
                    throw new \InvalidArgumentException(
                        \sprintf('RAG tool "%s" references non-existent collection "%s"', $ragTool->id, $ragTool->collection)
                    );
                }
                
                $this->toolRegistry->register($ragTool);
                $this->logger?->debug('RAG tool registered', ['id' => $ragTool->id]);
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to parse RAG tool', ['error' => $e->getMessage()]);
                throw $e;
            }
        }
    }
}
```

### 7. Updated ActionsBootloader

Replace static RAG tool registration with dynamic:

```php
// src/McpServer/ActionsBootloader.php

// In defineActions() method, replace static RAG tools registration:

// RAG Tools - register dynamic tools from configuration
if ($ragRegistry->isEnabled()) {
    // Always include manage tool
    $actions[] = RagManageAction::class;

    // Check if custom RAG tools are defined
    if ($container->has(RagToolRegistryInterface::class)) {
        $ragToolRegistry = $container->get(RagToolRegistryInterface::class);
        
        if ($ragToolRegistry->hasTools()) {
            // Use dynamic tools from configuration
            // These are registered separately via RagToolFactory
            $this->logger->debug('Using dynamic RAG tools from configuration');
        } else {
            // Fallback to static tools if no custom tools defined
            $actions[] = RagStoreAction::class;
            $actions[] = RagSearchAction::class;
        }
    } else {
        // Legacy: use static tools
        $actions[] = RagStoreAction::class;
        $actions[] = RagSearchAction::class;
    }
}
```

Also add dynamic tool registration:

```php
// Add method to register dynamic RAG tools in MCP registry
private function registerDynamicRagTools(/* ... */): void
{
    $factory = $this->container->get(RagToolFactory::class);
    $tools = $factory->createAll();

    foreach ($tools['search'] as $action) {
        // Register as MCP tool with custom name/description
        $this->mcpRegistry->registerTool(
            name: $action->getToolName(),
            description: $action->getToolDescription(),
            inputSchema: $action->getInputSchema(),
            handler: $action(...),
        );
    }

    foreach ($tools['store'] as $action) {
        $this->mcpRegistry->registerTool(
            name: $action->getToolName(),
            description: $action->getToolDescription(),
            inputSchema: $action->getInputSchema(),
            handler: $action(...),
        );
    }
}
```

## Definition of Done

- [ ] `RagToolRegistryInterface` and `RagToolRegistry` created
- [ ] `DynamicRagSearchAction` works with configurable collection and custom name/description
- [ ] `DynamicRagStoreAction` works with configurable collection and custom name/description
- [ ] `RagToolFactory` creates actions from all registered RAG tools
- [ ] Factory validates collection existence before creating actions
- [ ] `RagParserPlugin` registers RAG tools in `RagToolRegistry`
- [ ] `ActionsBootloader` registers dynamic tools when custom RAG tools are defined
- [ ] Fallback to static tools when no custom RAG tools configured
- [ ] `RagManageAction` always registered when RAG is enabled
- [ ] Dynamic tools have correct input schemas
- [ ] Unit tests verify factory creates correct actions
- [ ] Integration test verifies tools appear in MCP tool list

## Dependencies

**Requires**: Stage 3 (Service Layer), Stage 4 (Tool Parser)
**Enables**: Stage 6 (CLI Commands), Stage 7 (Integration)
