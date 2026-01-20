# Stage 4: Change Handlers

## Objective

Implement handlers that process configuration changes for specific sections (tools, prompts) and update the appropriate registries.

---

## Problem

When configuration changes are detected, we need to:
1. Parse the changed configuration section
2. Update the appropriate registry (add/remove/modify items)
3. Trigger MCP notifications to inform connected clients

Each section (tools, prompts, resources) has different:
- Data structures
- Registries
- Parser plugins

---

## Solution

Create a handler interface and implementations for each configuration section that can process changes and update registries.

---

## Files to Create

### Directory Structure

```
src/Watcher/
└── Handler/
    ├── ChangeHandlerInterface.php
    ├── ToolsChangeHandler.php
    └── PromptsChangeHandler.php
```

---

## Interface Definition

### `src/Watcher/Handler/ChangeHandlerInterface.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiff;

/**
 * Handles configuration changes for a specific section.
 */
interface ChangeHandlerInterface
{
    /**
     * Get the configuration section this handler manages.
     * Must match key in context.yaml (e.g., 'tools', 'prompts').
     */
    public function getSection(): string;

    /**
     * Apply changes from configuration diff.
     *
     * @param ConfigDiff $diff The calculated differences
     * @return bool True if any changes were applied
     */
    public function apply(ConfigDiff $diff): bool;

    /**
     * Full reload - clear and re-register all items.
     * Used when diff calculation is not possible.
     *
     * @param array $items New configuration items
     * @return bool True if any changes were made
     */
    public function reload(array $items): bool;
}
```

---

## Implementations

### 1. `src/Watcher/Handler/ToolsChangeHandler.php`

Handles changes to the `tools` section:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\ToolRegistryInterface;
use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiff;
use Mcp\Server\Contracts\ReferenceRegistryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ToolsChangeHandler implements ChangeHandlerInterface
{
    public function __construct(
        private ToolRegistryInterface $toolRegistry,
        private ReferenceRegistryInterface $mcpRegistry,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getSection(): string
    {
        return 'tools';
    }

    public function apply(ConfigDiff $diff): bool
    {
        if (!$diff->hasChanges()) {
            return false;
        }

        $this->logger->info('Applying tool changes', [
            'summary' => $diff->getSummary(),
        ]);

        // Process removals first
        foreach ($diff->removed as $id => $toolConfig) {
            $this->removeTool($id);
        }

        // Process additions
        foreach ($diff->added as $id => $toolConfig) {
            $this->addTool($toolConfig);
        }

        // Process modifications (remove old, add new)
        foreach ($diff->modified as $id => $toolConfig) {
            $this->removeTool($id);
            $this->addTool($toolConfig);
        }

        // Emit MCP notification
        $this->notifyListChanged();

        return true;
    }

    public function reload(array $items): bool
    {
        $this->logger->info('Full tool reload', [
            'count' => count($items),
        ]);

        // Clear existing tools
        $this->toolRegistry->clear();

        // Register all tools
        foreach ($items as $toolConfig) {
            $this->addTool($toolConfig);
        }

        // Emit MCP notification
        $this->notifyListChanged();

        return true;
    }

    private function addTool(array $config): void
    {
        try {
            $tool = ToolDefinition::fromArray($config);
            $this->toolRegistry->register($tool);

            $this->logger->debug('Tool registered', ['id' => $tool->id]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register tool', [
                'config' => $config,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeTool(string $id): void
    {
        if ($this->toolRegistry->remove($id)) {
            $this->logger->debug('Tool removed', ['id' => $id]);
        }
    }

    private function notifyListChanged(): void
    {
        // Emit event that Protocol listens to
        $this->mcpRegistry->emit('list_changed', ['tools']);

        $this->logger->debug('Emitted tools list_changed notification');
    }
}
```

### 2. `src/Watcher/Handler/PromptsChangeHandler.php`

Handles changes to the `prompts` section:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Butschster\ContextGenerator\McpServer\Prompt\PromptConfigFactory;
use Butschster\ContextGenerator\McpServer\Prompt\PromptRegistryInterface;
use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiff;
use Mcp\Server\Contracts\ReferenceRegistryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class PromptsChangeHandler implements ChangeHandlerInterface
{
    public function __construct(
        private PromptRegistryInterface $promptRegistry,
        private PromptConfigFactory $promptFactory,
        private ReferenceRegistryInterface $mcpRegistry,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getSection(): string
    {
        return 'prompts';
    }

    public function apply(ConfigDiff $diff): bool
    {
        if (!$diff->hasChanges()) {
            return false;
        }

        $this->logger->info('Applying prompt changes', [
            'summary' => $diff->getSummary(),
        ]);

        // Process removals first
        foreach ($diff->removed as $id => $promptConfig) {
            $this->removePrompt($id);
        }

        // Process additions
        foreach ($diff->added as $id => $promptConfig) {
            $this->addPrompt($promptConfig);
        }

        // Process modifications
        foreach ($diff->modified as $id => $promptConfig) {
            $this->removePrompt($id);
            $this->addPrompt($promptConfig);
        }

        // Emit MCP notification
        $this->notifyListChanged();

        return true;
    }

    public function reload(array $items): bool
    {
        $this->logger->info('Full prompt reload', [
            'count' => count($items),
        ]);

        // Clear existing prompts
        $this->promptRegistry->clear();

        // Register all prompts
        foreach ($items as $promptConfig) {
            $this->addPrompt($promptConfig);
        }

        // Emit MCP notification
        $this->notifyListChanged();

        return true;
    }

    private function addPrompt(array $config): void
    {
        try {
            $prompt = $this->promptFactory->createFromArray($config);
            $this->promptRegistry->register($prompt);

            $this->logger->debug('Prompt registered', ['id' => $prompt->id]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register prompt', [
                'config' => $config,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removePrompt(string $id): void
    {
        if ($this->promptRegistry->remove($id)) {
            $this->logger->debug('Prompt removed', ['id' => $id]);
        }
    }

    private function notifyListChanged(): void
    {
        $this->mcpRegistry->emit('list_changed', ['prompts']);

        $this->logger->debug('Emitted prompts list_changed notification');
    }
}
```

---

## Handler Registry

For managing multiple handlers, create a simple registry:

### `src/Watcher/Handler/ChangeHandlerRegistry.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

final class ChangeHandlerRegistry
{
    /** @var array<string, ChangeHandlerInterface> */
    private array $handlers = [];

    public function register(ChangeHandlerInterface $handler): void
    {
        $this->handlers[$handler->getSection()] = $handler;
    }

    public function get(string $section): ?ChangeHandlerInterface
    {
        return $this->handlers[$section] ?? null;
    }

    public function has(string $section): bool
    {
        return isset($this->handlers[$section]);
    }

    /**
     * @return array<string, ChangeHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * @return string[]
     */
    public function getSupportedSections(): array
    {
        return array_keys($this->handlers);
    }
}
```

---

## Integration with MCP Registry

The handlers need to emit events that the MCP `Protocol` listens to. The existing infrastructure in `llm/mcp-server` already supports this:

```php
// vendor/llm/mcp-server/src/Protocol.php
$this->registry->on('list_changed', function (string $listType): void {
    $this->handleListChanged($listType);
});
```

When handler calls:
```php
$this->mcpRegistry->emit('list_changed', ['tools']);
```

Protocol sends `ToolListChangedNotification` to all subscribed clients.

---

## Implementation Details

### Error Handling

Handlers should be resilient to individual item failures:

```php
foreach ($diff->added as $id => $config) {
    try {
        $this->addTool($config);
    } catch (\Throwable $e) {
        // Log error but continue with other items
        $this->logger->error('Failed to add tool', [...]);
    }
}
```

### Order of Operations

Always process changes in this order:
1. **Removals** - Remove old items first
2. **Additions** - Add new items
3. **Modifications** - Implemented as remove + add

This ensures no ID conflicts during updates.

### Notification Batching

Single notification is sent after all changes are applied, not per-item. This prevents notification spam.

---

## Testing

### Unit Tests for ToolsChangeHandler

```php
public function test_apply_adds_new_tools(): void
{
    $registry = new ToolRegistry();
    $mcpRegistry = $this->createMock(ReferenceRegistryInterface::class);

    $mcpRegistry->expects($this->once())
        ->method('emit')
        ->with('list_changed', ['tools']);

    $handler = new ToolsChangeHandler($registry, $mcpRegistry);

    $diff = new ConfigDiff(
        added: [
            'new-tool' => [
                'id' => 'new-tool',
                'name' => 'new-tool',
                'description' => 'A new tool',
                'command' => ['name' => 'echo', 'args' => ['hello']],
            ],
        ],
    );

    $result = $handler->apply($diff);

    $this->assertTrue($result);
    $this->assertTrue($registry->has('new-tool'));
}

public function test_apply_removes_tools(): void
{
    $registry = new ToolRegistry();
    $mcpRegistry = $this->createMock(ReferenceRegistryInterface::class);

    // Pre-register a tool
    $registry->register(ToolDefinition::fromArray([
        'id' => 'old-tool',
        'name' => 'old-tool',
        'description' => 'Old tool',
        'command' => ['name' => 'echo'],
    ]));

    $handler = new ToolsChangeHandler($registry, $mcpRegistry);

    $diff = new ConfigDiff(
        removed: ['old-tool' => ['id' => 'old-tool']],
    );

    $result = $handler->apply($diff);

    $this->assertTrue($result);
    $this->assertFalse($registry->has('old-tool'));
}

public function test_apply_modifies_tools(): void
{
    $registry = new ToolRegistry();
    $mcpRegistry = $this->createMock(ReferenceRegistryInterface::class);

    // Pre-register a tool
    $registry->register(ToolDefinition::fromArray([
        'id' => 'my-tool',
        'name' => 'my-tool',
        'description' => 'Original description',
        'command' => ['name' => 'echo'],
    ]));

    $handler = new ToolsChangeHandler($registry, $mcpRegistry);

    $diff = new ConfigDiff(
        modified: [
            'my-tool' => [
                'id' => 'my-tool',
                'name' => 'my-tool',
                'description' => 'Updated description',
                'command' => ['name' => 'echo'],
            ],
        ],
    );

    $handler->apply($diff);

    $tool = $registry->get('my-tool');
    $this->assertEquals('Updated description', $tool->description);
}

public function test_apply_returns_false_for_no_changes(): void
{
    $registry = new ToolRegistry();
    $mcpRegistry = $this->createMock(ReferenceRegistryInterface::class);

    $mcpRegistry->expects($this->never())->method('emit');

    $handler = new ToolsChangeHandler($registry, $mcpRegistry);

    $diff = ConfigDiff::empty();

    $result = $handler->apply($diff);

    $this->assertFalse($result);
}

public function test_reload_clears_and_registers(): void
{
    $registry = new ToolRegistry();
    $mcpRegistry = $this->createMock(ReferenceRegistryInterface::class);

    // Pre-register tools
    $registry->register(ToolDefinition::fromArray([
        'id' => 'old-tool',
        'name' => 'old-tool',
        'description' => 'Will be removed',
        'command' => ['name' => 'echo'],
    ]));

    $handler = new ToolsChangeHandler($registry, $mcpRegistry);

    $handler->reload([
        [
            'id' => 'new-tool',
            'name' => 'new-tool',
            'description' => 'New tool',
            'command' => ['name' => 'echo'],
        ],
    ]);

    $this->assertFalse($registry->has('old-tool'));
    $this->assertTrue($registry->has('new-tool'));
}
```

### Unit Tests for PromptsChangeHandler

Similar pattern to ToolsChangeHandler tests.

### Unit Tests for ChangeHandlerRegistry

```php
public function test_registers_and_retrieves_handler(): void
{
    $registry = new ChangeHandlerRegistry();
    $handler = $this->createMock(ChangeHandlerInterface::class);
    $handler->method('getSection')->willReturn('tools');

    $registry->register($handler);

    $this->assertTrue($registry->has('tools'));
    $this->assertSame($handler, $registry->get('tools'));
}

public function test_returns_null_for_unknown_section(): void
{
    $registry = new ChangeHandlerRegistry();

    $this->assertNull($registry->get('unknown'));
}

public function test_lists_supported_sections(): void
{
    $registry = new ChangeHandlerRegistry();

    $toolsHandler = $this->createMock(ChangeHandlerInterface::class);
    $toolsHandler->method('getSection')->willReturn('tools');

    $promptsHandler = $this->createMock(ChangeHandlerInterface::class);
    $promptsHandler->method('getSection')->willReturn('prompts');

    $registry->register($toolsHandler);
    $registry->register($promptsHandler);

    $sections = $registry->getSupportedSections();

    $this->assertContains('tools', $sections);
    $this->assertContains('prompts', $sections);
}
```

---

## Acceptance Criteria

- [ ] `ToolsChangeHandler` adds new tools from diff
- [ ] `ToolsChangeHandler` removes deleted tools from diff
- [ ] `ToolsChangeHandler` updates modified tools
- [ ] `ToolsChangeHandler` emits `list_changed` notification
- [ ] `ToolsChangeHandler::reload()` clears and re-registers all
- [ ] `PromptsChangeHandler` works same as ToolsChangeHandler
- [ ] `ChangeHandlerRegistry` manages multiple handlers
- [ ] Handlers continue processing on individual item errors
- [ ] Single notification sent per apply/reload (not per item)

---

## Notes

- Documents/resources handler can be added later if needed
- Consider adding metrics (items added/removed count)
- Handler could validate config before applying (future enhancement)
- May need to update MCP Registry (`Mcp\Server\Registry`) tools list too
