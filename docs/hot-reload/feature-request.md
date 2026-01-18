# Feature Request: Hot-Reload for MCP Server Configuration

## Summary

Implement automatic hot-reload capability for the MCP server that detects changes in `context.yaml` configuration files and dynamically updates tools, prompts, and resources without requiring server restart.

---

## Problem Statement

### Current Behavior

When the MCP server starts, it loads the configuration from `context.yaml` once during initialization:

```php
// src/McpServer/ActionsBootloader.php
ServerRunnerInterface::class => function (
    McpConfig $config,
    ServerRunner $factory,
    ConfigLoaderInterface $loader,
) {
    $loader->load();  // ← Configuration loaded ONCE at startup
    // ...
}
```

After this initial load, any changes to `context.yaml` (adding new tools, modifying prompts, updating resources) are **not reflected** in the running server until a full restart.

### User Pain Points

1. **Workflow Disruption**: Developers must manually restart the MCP server every time they add or modify tools in `context.yaml`
2. **Client Reconnection**: MCP clients (Claude Desktop, Cursor, VS Code, etc.) lose their connection and must re-initialize
3. **Context Loss**: Ongoing conversations in AI assistants may lose context due to server restart
4. **Development Friction**: Rapid iteration on custom tools becomes tedious

### Use Case Example

A developer is building a custom MCP tool for their project:

```yaml
# context.yaml
tools:
  - id: my-custom-tool
    name: my-tool
    description: "Does something useful"
    command:
      name: php
      args: ["artisan", "my:command"]
```

They realize they need to add another tool. Currently, they must:
1. Edit `context.yaml`
2. Stop the MCP server
3. Restart the MCP server
4. Wait for client reconnection
5. Re-establish conversation context

With hot-reload, steps 2-5 would be eliminated.

---

## Proposed Solution

### Overview

Implement a **ConfigWatcher** system that:
1. Monitors `context.yaml` and all imported configuration files for changes
2. Detects modifications using efficient file watching mechanisms
3. Parses only the changed sections of configuration
4. Updates the appropriate registries (tools, prompts, resources)
5. Emits MCP protocol notifications to inform connected clients

### MCP Protocol Support

The MCP specification already supports dynamic list updates through notifications:

| Notification | Purpose |
|--------------|---------|
| `notifications/tools/list_changed` | Informs clients that available tools have changed |
| `notifications/prompts/list_changed` | Informs clients that available prompts have changed |
| `notifications/resources/list_changed` | Informs clients that available resources have changed |

These notifications are already implemented in the schema:
- `PhpMcp\Schema\Notification\ToolListChangedNotification`
- `PhpMcp\Schema\Notification\PromptListChangedNotification`
- `PhpMcp\Schema\Notification\ResourceListChangedNotification`

The `Mcp\Server\Registry` already has the infrastructure to emit `list_changed` events:

```php
// vendor/llm/mcp-server/src/Protocol.php
$this->registry->on('list_changed', function (string $listType): void {
    $this->handleListChanged($listType);
});
```

---

## Technical Design

### Architecture

```
src/Watcher/
├── ConfigWatcherInterface.php           # Main watcher contract
├── ConfigWatcher.php                    # Implementation with file monitoring
├── Strategy/
│   ├── WatchStrategyInterface.php       # File watching strategy contract
│   ├── InotifyWatchStrategy.php         # Linux inotify-based (if ext-inotify available)
│   ├── PollingWatchStrategy.php         # Universal polling fallback
│   └── WatchStrategyFactory.php         # Auto-selects best strategy
├── Handler/
│   ├── ChangeHandlerInterface.php       # Handler contract for config sections
│   ├── ToolsChangeHandler.php           # Handles tools section changes
│   ├── PromptsChangeHandler.php         # Handles prompts section changes
│   └── ResourcesChangeHandler.php       # Handles resources section changes (documents)
├── Diff/
│   ├── ConfigDiffCalculator.php         # Calculates what changed between configs
│   └── ConfigDiff.php                   # DTO representing changes
└── ConfigWatcherBootloader.php          # Spiral bootloader for DI
```

### Component Responsibilities

#### 1. ConfigWatcher

The main orchestrator that:
- Tracks all configuration files (main + imports)
- Delegates file monitoring to the appropriate strategy
- Triggers handlers when changes are detected

```php
interface ConfigWatcherInterface
{
    public function start(string $mainConfigPath, array $importedPaths = []): void;
    public function tick(): void;
    public function stop(): void;
}
```

#### 2. Watch Strategies

**InotifyWatchStrategy** (Linux, requires `ext-inotify`):
- Uses kernel-level file system events
- Zero CPU overhead when idle
- Immediate notification on changes

**PollingWatchStrategy** (Universal fallback):
- Checks file `mtime` at configurable intervals (default: 2 seconds)
- Works on all platforms
- Slightly higher CPU usage but negligible for config files

#### 3. Change Handlers

Each handler is responsible for:
1. Parsing its section from the new configuration
2. Comparing with current registry state
3. Applying additions, removals, and modifications
4. Triggering appropriate notifications

```php
interface ChangeHandlerInterface
{
    public function getSection(): string;
    public function apply(ConfigDiff $diff): bool;
}
```

#### 4. ConfigDiff

Represents the difference between two configurations:

```php
final readonly class ConfigDiff
{
    public function __construct(
        public array $added,
        public array $removed,
        public array $modified,
        public array $unchanged,
    ) {}

    public function hasChanges(): bool;
}
```

### Integration Points

#### StdioTransport Integration

The watcher's `tick()` method is called within the transport's event loop during idle time (stream_select timeout).

#### Registry Updates

The handlers update both local registries (`ToolRegistry`, `PromptRegistry`) and emit events through the MCP Registry for protocol notifications.

---

## Files Involved

### Files to Create (in `ctx/mcp-server` package)

| File | Purpose |
|------|---------|
| `src/Watcher/ConfigWatcherInterface.php` | Main watcher contract |
| `src/Watcher/ConfigWatcher.php` | Watcher implementation |
| `src/Watcher/Strategy/WatchStrategyInterface.php` | File watching strategy contract |
| `src/Watcher/Strategy/InotifyWatchStrategy.php` | inotify-based watching |
| `src/Watcher/Strategy/PollingWatchStrategy.php` | Polling-based watching |
| `src/Watcher/Strategy/WatchStrategyFactory.php` | Strategy selection |
| `src/Watcher/Handler/ChangeHandlerInterface.php` | Change handler contract |
| `src/Watcher/Handler/ToolsChangeHandler.php` | Tools section handler |
| `src/Watcher/Handler/PromptsChangeHandler.php` | Prompts section handler |
| `src/Watcher/Diff/ConfigDiffCalculator.php` | Diff calculation logic |
| `src/Watcher/Diff/ConfigDiff.php` | Diff data structure |
| `src/Watcher/ConfigWatcherBootloader.php` | DI configuration |

### Files to Modify

| File | Changes |
|------|---------|
| `src/Transport/StdioTransport.php` | Add `tick()` call in event loop |
| `src/ServerRunner.php` | Initialize and inject ConfigWatcher |
| `src/Tool/ToolRegistry.php` | Add `remove()` and `clear()` methods |
| `src/Tool/ToolRegistryInterface.php` | Add method signatures |
| `src/Prompt/PromptRegistry.php` | Add `remove()` and `clear()` methods |
| `src/Prompt/PromptRegistryInterface.php` | Add method signatures |

---

## Key References

### MCP Protocol Specification

The MCP protocol defines list change notifications:
- [MCP Specification - Notifications](https://spec.modelcontextprotocol.io/specification/server/notifications/)

### Existing Infrastructure

**Notification Classes** (already implemented):
```
vendor/php-mcp/schema/src/Notification/
├── ToolListChangedNotification.php
├── PromptListChangedNotification.php
└── ResourceListChangedNotification.php
```

**Registry Event System**:
```php
// vendor/llm/mcp-server/src/Registry.php
final class Registry implements ReferenceRegistryInterface
{
    use EventEmitterTrait;  // From evenement/evenement
}
```

**Protocol Notification Handler**:
```php
// vendor/llm/mcp-server/src/Protocol.php
public function handleListChanged(string $listType): void
{
    $notification = match ($listType) {
        'tools' => ToolListChangedNotification::make(),
        'prompts' => PromptListChangedNotification::make(),
        'resources' => ResourceListChangedNotification::make(),
    };

    foreach ($subscribers as $sessionId) {
        $this->sendNotification($notification, $sessionId);
    }
}
```

---

## Implementation Considerations

### 1. Debouncing

File editors often trigger multiple write events for a single save. Implement debouncing with ~200ms delay.

### 2. Error Handling

Invalid YAML after edit should not crash the server - keep previous valid configuration.

### 3. Import File Tracking

When imports change, the watcher must update its file list dynamically.

### 4. Configuration Option

Allow users to disable hot-reload via environment variable:
```bash
MCP_HOT_RELOAD=false ctx server
```

---

## Success Criteria

1. **Functional**: Tools/prompts/resources update within 3 seconds of config file save
2. **Reliable**: No server crashes due to config errors during reload
3. **Efficient**: < 1% CPU overhead when idle (polling mode)
4. **Compatible**: Works with Claude Desktop, Cursor, and other MCP clients
5. **Transparent**: Clear logging of reload events for debugging

---

## References

- [MCP Specification](https://spec.modelcontextprotocol.io/)
- [PHP inotify extension](https://www.php.net/manual/en/book.inotify.php)
- [Evenement - Event Emitter](https://github.com/igorw/evenement)
