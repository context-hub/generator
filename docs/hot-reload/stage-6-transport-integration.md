# Stage 6: Transport Integration

## Objective

Integrate the `ConfigWatcher` into the MCP server's event loop so that configuration changes are automatically detected and processed during server operation.

---

## Problem

The `ConfigWatcher` has a `tick()` method that needs to be called periodically. The MCP server uses `StdioTransport` which runs a blocking event loop. We need to:

1. Inject `ConfigWatcher` into the server startup flow
2. Call `tick()` during idle periods in the transport loop
3. Initialize the watcher with correct config paths
4. Clean up on server shutdown

---

## Solution

Modify the transport and server runner to integrate the config watcher into the existing event loop.

---

## Files to Modify

### 1. `src/Transport/StdioTransport.php`

### 2. `src/ServerRunner.php`

---

## Implementation

### 1. StdioTransport Modification

Add watcher tick to the event loop:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Transport;

use Butschster\ContextGenerator\McpServer\Watcher\ConfigWatcherInterface;
use Evenement\EventEmitter;
use Mcp\Server\Contracts\ServerTransportInterface;
// ... other imports ...

final class StdioTransport extends EventEmitter implements ServerTransportInterface
{
    // ... existing properties ...

    private ?ConfigWatcherInterface $configWatcher = null;

    public function __construct(
        private $input = \STDIN,
        private $output = \STDOUT,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Set the config watcher for hot-reload support.
     */
    public function setConfigWatcher(ConfigWatcherInterface $watcher): void
    {
        $this->configWatcher = $watcher;
    }

    public function listen(): void
    {
        // ... existing initialization code ...

        while (!\feof($this->input) && !$this->closing) {
            $read = [$this->input];
            $write = null;
            $except = null;

            // Short timeout to check watcher periodically
            $result = @\stream_select($read, $write, $except, 1);

            if ($result === false) {
                $this->logger->error('stream_select() failed');
                break;
            }

            if ($result === 0) {
                // Timeout - no data available
                // Perfect time to check for config changes!
                $this->tickConfigWatcher();
                continue;
            }

            // ... existing message processing code ...

            $chunk = \fread($this->input, self::READ_CHUNK_SIZE);

            // ... rest of existing code ...
        }

        $this->logger->info('StdioTransport finished listening.');
    }

    /**
     * Non-blocking config watcher tick.
     */
    private function tickConfigWatcher(): void
    {
        if ($this->configWatcher !== null) {
            try {
                $this->configWatcher->tick();
            } catch (\Throwable $e) {
                $this->logger->error('Config watcher error', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function close(): void
    {
        // Stop config watcher on transport close
        if ($this->configWatcher !== null) {
            $this->configWatcher->stop();
            $this->configWatcher = null;
        }

        // ... existing close code ...
    }

    // ... rest of existing methods ...
}
```

### 2. ServerRunner Modification

Initialize watcher during server startup:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Butschster\ContextGenerator\McpServer\Transport\StdioTransport;
use Butschster\ContextGenerator\McpServer\Watcher\ConfigWatcherInterface;
use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Server;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

#[Singleton]
final class ServerRunner implements ServerRunnerInterface
{
    private array $actions = [];

    public function __construct(
        private readonly ScopeInterface $scope,
    ) {}

    public function registerAction(string $class): void
    {
        $this->actions[] = $class;
    }

    public function run(string $name): void
    {
        $this->scope->runScope(
            bindings: new Scope(name: 'mcp-server'),
            scope: function (
                RouteRegistrar $registrar,
                McpItemsRegistry $registry,
                ExceptionReporterInterface $reporter,
                Server $server,
                ServerTransportInterface $transport,
                ConfigWatcherInterface $configWatcher,
                ConfigWatcherConfig $watcherConfig, // New: holds paths
            ) use ($name): void {
                // Register all classes with MCP item attributes
                $registry->registerMany($this->actions);

                // Register all controllers for routing
                $registrar->registerControllers($this->actions);

                // Initialize config watcher with paths
                $configWatcher->start(
                    mainConfigPath: $watcherConfig->mainConfigPath,
                    importPaths: $watcherConfig->importPaths,
                );

                // Inject watcher into transport (if StdioTransport)
                if ($transport instanceof StdioTransport) {
                    $transport->setConfigWatcher($configWatcher);
                }

                try {
                    $server->listen($transport);
                } catch (\Throwable $e) {
                    $reporter->report($e);
                } finally {
                    // Ensure watcher is stopped on exit
                    $configWatcher->stop();
                }
            },
        );
    }
}
```

### 3. ConfigWatcherConfig DTO

Create a simple config holder:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

/**
 * Holds configuration paths for the watcher.
 * Populated during server initialization.
 */
final readonly class ConfigWatcherConfig
{
    public function __construct(
        public string $mainConfigPath,
        public array $importPaths = [],
    ) {}
}
```

### 4. Bootloader Updates

Update the main CTX project's `ActionsBootloader` to provide paths:

```php
// In src/McpServer/ActionsBootloader.php (main CTX project)

#[\Override]
public function defineSingletons(): array
{
    return [
        ServerRunnerInterface::class => function (
            McpConfig $config,
            ServerRunner $factory,
            ConfigLoaderInterface $loader,
            DirectoriesInterface $dirs,
        ) {
            // Load config and track import paths
            $registry = $loader->load();

            // Get import paths from resolved config
            $importPaths = $this->extractImportPaths($loader);

            foreach ($this->actions($config) as $action) {
                $factory->registerAction($action);
            }

            return $factory;
        },

        // Provide watcher config
        ConfigWatcherConfig::class => function (
            DirectoriesInterface $dirs,
            ConfigLoaderInterface $loader,
        ) {
            $mainConfigPath = (string) $dirs->getConfigPath();
            $importPaths = $this->extractImportPaths($loader);

            return new ConfigWatcherConfig(
                mainConfigPath: $mainConfigPath,
                importPaths: $importPaths,
            );
        },
    ];
}

private function extractImportPaths(ConfigLoaderInterface $loader): array
{
    // This requires exposing import paths from the loader
    // May need modification to ConfigLoader/ImportResolver
    return [];
}
```

---

## Import Path Extraction

To get the list of imported config files, we need to expose them from `ImportResolver`. Add to `ResolvedConfig`:

```php
// src/Config/Import/ResolvedConfig.php

final readonly class ResolvedConfig
{
    public function __construct(
        public array $config,
        public array $imports = [], // SourceConfigInterface[]
    ) {}

    /**
     * Get absolute paths of all imported files.
     *
     * @return string[]
     */
    public function getImportPaths(): array
    {
        $paths = [];

        foreach ($this->imports as $import) {
            if ($import instanceof LocalSourceConfig) {
                $paths[] = $import->getAbsolutePath();
            }
        }

        return $paths;
    }
}
```

---

## Alternative: Transport-Agnostic Approach

If modifying `StdioTransport` is not ideal (it's in the shared package), use an event-based approach:

### Using Timer Events

```php
// In ServerRunner
$timer = new PeriodicTimer(1.0, function () use ($configWatcher) {
    $configWatcher->tick();
});

$transport->on('ready', function () use ($timer) {
    $timer->start();
});

$transport->on('close', function () use ($timer, $configWatcher) {
    $timer->stop();
    $configWatcher->stop();
});
```

However, this requires a timer implementation compatible with the blocking loop.

### Recommended Approach

The simplest and most reliable approach is the direct integration shown above:
1. Add `setConfigWatcher()` to `StdioTransport`
2. Call `tick()` during stream_select timeout
3. This leverages the existing 1-second timeout for zero additional overhead

---

## Sequence Diagram

```
┌─────────┐     ┌──────────────┐     ┌───────────────┐     ┌─────────┐
│ Client  │     │StdioTransport│     │ ConfigWatcher │     │ Handler │
└────┬────┘     └──────┬───────┘     └───────┬───────┘     └────┬────┘
     │                 │                      │                  │
     │                 │──── listen() ────────│                  │
     │                 │                      │                  │
     │                 │◄─── stream_select ───│                  │
     │                 │     (timeout=1s)     │                  │
     │                 │                      │                  │
     │                 │ [timeout, no data]   │                  │
     │                 │                      │                  │
     │                 │───── tick() ─────────►                  │
     │                 │                      │                  │
     │                 │                      │─── check() ──────│
     │                 │                      │   (file mtime)   │
     │                 │                      │                  │
     │                 │                      │ [changes found]  │
     │                 │                      │                  │
     │                 │                      │── apply(diff) ──►│
     │                 │                      │                  │
     │                 │                      │                  │── update registry
     │                 │                      │                  │
     │                 │                      │                  │── emit('list_changed')
     │                 │                      │◄─────────────────│
     │                 │                      │                  │
     │◄──────────────────────────────────────────────────────────│
     │       ToolListChangedNotification                         │
     │                 │                      │                  │
```

---

## Testing

### Integration Test with MCP Inspector

```bash
# Terminal 1: Start server with hot-reload
MCP_HOT_RELOAD=true ctx server

# Terminal 2: Connect MCP Inspector
npx @modelcontextprotocol/inspector

# Terminal 3: Modify config
cat >> context.yaml << 'EOF'
  - id: hot-reload-test
    name: hot-reload-test
    description: Testing hot reload
    command:
      name: echo
      args: ["Hot reload works!"]
EOF

# Observe in Inspector:
# 1. ToolListChangedNotification received
# 2. tools/list shows new tool
```

### Unit Tests

```php
public function test_transport_calls_watcher_on_timeout(): void
{
    $watcher = $this->createMock(ConfigWatcherInterface::class);
    $watcher->expects($this->atLeastOnce())->method('tick');

    $transport = new StdioTransport(
        input: $this->createMockInputStream(),
        output: fopen('php://memory', 'w'),
    );
    $transport->setConfigWatcher($watcher);

    // Simulate short listen with timeout
    // This is tricky to test - may need to mock stream_select
}

public function test_watcher_stopped_on_transport_close(): void
{
    $watcher = $this->createMock(ConfigWatcherInterface::class);
    $watcher->expects($this->once())->method('stop');

    $transport = new StdioTransport();
    $transport->setConfigWatcher($watcher);

    $transport->close();
}

public function test_server_initializes_watcher(): void
{
    $watcher = $this->createMock(ConfigWatcherInterface::class);
    $watcher->expects($this->once())
        ->method('start')
        ->with('/path/to/context.yaml', []);

    // Test via ServerRunner with mocked dependencies
}
```

### E2E Test

```php
public function test_hot_reload_updates_tools(): void
{
    // 1. Start server with test config
    // 2. Connect test client
    // 3. Verify initial tools
    // 4. Modify config file
    // 5. Wait for notification
    // 6. Verify new tools available
    // 7. Clean up
}
```

---

## Acceptance Criteria

- [ ] `StdioTransport` calls `tick()` during idle periods
- [ ] Watcher initialized with correct config paths
- [ ] Watcher stopped on transport close
- [ ] No performance impact (tick < 1ms)
- [ ] MCP Inspector receives `ToolListChangedNotification`
- [ ] New tools available after config change
- [ ] Server continues working if watcher fails
- [ ] Works with both inotify and polling strategies

---

## Rollout Plan

1. **Phase 1**: Deploy with `MCP_HOT_RELOAD=false` by default
2. **Phase 2**: Enable for beta users
3. **Phase 3**: Enable by default after validation

---

## Monitoring

Add metrics for observability:

```php
// In ConfigWatcher
private int $reloadCount = 0;
private float $lastReloadTime = 0;

public function getStats(): array
{
    return [
        'reload_count' => $this->reloadCount,
        'last_reload_time' => $this->lastReloadTime,
        'watching' => $this->isWatching(),
        'files_watched' => count($this->importPaths) + 1,
    ];
}
```

Expose via MCP resource or logging.

---

## Notes

- The 1-second stream_select timeout is already present - no additional delay
- Hot-reload adds ~0.1ms overhead per tick (polling check)
- Consider adding a health check endpoint for monitoring
- Future: HTTP/SSE transport can use native file watching events
