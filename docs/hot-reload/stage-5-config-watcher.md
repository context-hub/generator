# Stage 5: Config Watcher Orchestra- `src/Watcher/Diff/ConfigDiff.php`
- `src/Watcher/Diff/ConfigDiffCalculator.php`

### Stage 4
- `src/Watcher/Handler/ChangeHandlerInterface.php`
- `src/Watcher/Handler/ToolsChangeHandler.php`
- `src/Watcher/Handler/PromptsChangeHandler.php`

### Stage 5
- `src/Watcher/ConfigWatcherInterface.php`
- `src/Watcher/ConfigWatcher.php`
- `src/Watcher/ConfigWatcherBootloader.php`

### Stage 6
- `src/Transport/StdioTransport.php` (modify)tor

## Objective

Create the main `ConfigWatcher` class that orchestrates file watching, change detection, and handler execution with proper debouncing and error handling.

---

## Problem

We have individual components:
- File watch strategies (Stage 2)
- Diff calculator (Stage 3)
- Change handlers (Stage 4)

Now we need an orchestrator that:
1. Manages the watch strategy lifecycle
2. Tracks main config and imported files
3. Debounces rapid file changes
4. Loads and parses config when changes detected
5. Calculates diffs and delegates to handlers
6. Handles errors gracefully

---

## Solution

Create a `ConfigWatcher` that ties all components together with a simple `tick()` method that can be called from the event loop.

---

## Files to Create

### Directory Structure

```
src/Watcher/
├── ConfigWatcherInterface.php
├── ConfigWatcher.php
├── ConfigLoaderFactory.php
└── ConfigWatcherBootloader.php
```

---

## Interface Definition

### `src/Watcher/ConfigWatcherInterface.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

interface ConfigWatcherInterface
{
    /**
     * Start watching configuration files.
     *
     * @param string $mainConfigPath Path to main context.yaml
     * @param array<string> $importPaths Paths to imported config files
     */
    public function start(string $mainConfigPath, array $importPaths = []): void;

    /**
     * Check for changes and process them (non-blocking).
     * Should be called periodically from event loop.
     */
    public function tick(): void;

    /**
     * Stop watching and release resources.
     */
    public function stop(): void;

    /**
     * Check if watcher is currently active.
     */
    public function isWatching(): bool;

    /**
     * Update the list of imported files to watch.
     */
    public function updateImports(array $importPaths): void;
}
```

---

## Main Implementation

### `src/Watcher/ConfigWatcher.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiffCalculator;
use Butschster\ContextGenerator\McpServer\Watcher\Handler\ChangeHandlerRegistry;
use Butschster\ContextGenerator\McpServer\Watcher\Strategy\WatchStrategyFactory;
use Butschster\ContextGenerator\McpServer\Watcher\Strategy\WatchStrategyInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ConfigWatcher implements ConfigWatcherInterface
{
    private ?WatchStrategyInterface $strategy = null;
    private ?string $mainConfigPath = null;
    private array $importPaths = [];
    private array $lastConfig = [];
    private float $lastChangeTime = 0;
    private bool $pendingReload = false;

    private const int DEBOUNCE_MS = 200;

    public function __construct(
        private readonly WatchStrategyFactory $strategyFactory,
        private readonly ConfigDiffCalculator $diffCalculator,
        private readonly ChangeHandlerRegistry $handlerRegistry,
        private readonly ConfigLoaderFactory $configLoaderFactory,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $enabled = true,
    ) {}

    public function start(string $mainConfigPath, array $importPaths = []): void
    {
        if (!$this->enabled) {
            $this->logger->info('Config watcher is disabled');
            return;
        }

        $this->mainConfigPath = $mainConfigPath;
        $this->importPaths = $importPaths;

        $this->strategy = $this->strategyFactory->create();

        $this->logger->info('Starting config watcher', [
            'strategy' => $this->strategy::class,
            'mainConfig' => $mainConfigPath,
            'imports' => count($importPaths),
        ]);

        $this->strategy->addFile($mainConfigPath);
        foreach ($importPaths as $path) {
            $this->strategy->addFile($path);
        }

        $this->loadCurrentConfig();
    }

    public function tick(): void
    {
        if ($this->strategy === null || !$this->enabled) {
            return;
        }

        // Check for pending debounced reload
        if ($this->pendingReload) {
            $now = microtime(true) * 1000;
            if ($now - $this->lastChangeTime >= self::DEBOUNCE_MS) {
                $this->processChanges();
                $this->pendingReload = false;
            }
            return;
        }

        // Check for file changes
        $changedFiles = $this->strategy->check();

        if (!empty($changedFiles)) {
            $this->logger->debug('Config file changes detected', [
                'files' => $changedFiles,
            ]);

            $this->lastChangeTime = microtime(true) * 1000;
            $this->pendingReload = true;
        }
    }

    public function stop(): void
    {
        if ($this->strategy !== null) {
            $this->strategy->stop();
            $this->strategy = null;
        }

        $this->mainConfigPath = null;
        $this->importPaths = [];
        $this->lastConfig = [];
        $this->pendingReload = false;

        $this->logger->info('Config watcher stopped');
    }

    public function isWatching(): bool
    {
        return $this->strategy !== null && $this->enabled;
    }

    public function updateImports(array $importPaths): void
    {
        if ($this->strategy === null) {
            return;
        }

        $newPaths = array_diff($importPaths, $this->importPaths);
        $removedPaths = array_diff($this->importPaths, $importPaths);

        foreach ($removedPaths as $path) {
            $this->strategy->removeFile($path);
        }

        foreach ($newPaths as $path) {
            $this->strategy->addFile($path);
        }

        $this->importPaths = $importPaths;

        $this->logger->debug('Import watch list updated', [
            'added' => count($newPaths),
            'removed' => count($removedPaths),
        ]);
    }

    private function processChanges(): void
    {
        $this->logger->info('Processing config changes');

        try {
            $newConfig = $this->loadConfig();

            if ($newConfig === null) {
                $this->logger->warning('Failed to load config, keeping current state');
                return;
            }

            $diffs = $this->diffCalculator->calculateAll($this->lastConfig, $newConfig);

            if (empty($diffs)) {
                $this->logger->debug('No effective changes detected');
                $this->lastConfig = $newConfig;
                return;
            }

            foreach ($diffs as $section => $diff) {
                $handler = $this->handlerRegistry->get($section);

                if ($handler === null) {
                    $this->logger->debug('No handler for section', ['section' => $section]);
                    continue;
                }

                try {
                    $handler->apply($diff);
                    $this->logger->info('Applied changes', [
                        'section' => $section,
                        'summary' => $diff->getSummary(),
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Handler failed', [
                        'section' => $section,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->lastConfig = $newConfig;

        } catch (\Throwable $e) {
            $this->logger->error('Config reload failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function loadConfig(): ?array
    {
        if ($this->mainConfigPath === null) {
            return null;
        }

        try {
            $loader = $this->configLoaderFactory->create($this->mainConfigPath);
            return $loader->loadRawConfig();
        } catch (\Throwable $e) {
            $this->logger->error('Config load error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function loadCurrentConfig(): void
    {
        $config = $this->loadConfig();
        if ($config !== null) {
            $this->lastConfig = $config;
        }
    }
}
```

---

## Config Loader Factory

### `src/Watcher/ConfigLoaderFactory.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;

interface ConfigLoaderFactory
{
    public function create(string $configPath): ConfigLoaderInterface;
}
```

---

## Bootloader

### `src/Watcher/ConfigWatcherBootloader.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiffCalculator;
use Butschster\ContextGenerator\McpServer\Watcher\Handler\ChangeHandlerRegistry;
use Butschster\ContextGenerator\McpServer\Watcher\Handler\PromptsChangeHandler;
use Butschster\ContextGenerator\McpServer\Watcher\Handler\ToolsChangeHandler;
use Butschster\ContextGenerator\McpServer\Watcher\Strategy\WatchStrategyFactory;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;

final class ConfigWatcherBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            ConfigWatcherInterface::class => ConfigWatcher::class,
            WatchStrategyFactory::class => WatchStrategyFactory::class,
            ConfigDiffCalculator::class => ConfigDiffCalculator::class,
            ChangeHandlerRegistry::class => ChangeHandlerRegistry::class,
        ];
    }

    public function boot(
        ChangeHandlerRegistry $registry,
        ToolsChangeHandler $toolsHandler,
        PromptsChangeHandler $promptsHandler,
    ): void {
        $registry->register($toolsHandler);
        $registry->register($promptsHandler);
    }
}
```

---

## Configuration Options

| Variable | Default | Description |
|----------|---------|-------------|
| `MCP_HOT_RELOAD` | `true` | Enable/disable hot-reload |
| `MCP_HOT_RELOAD_INTERVAL` | `2000` | Polling interval in ms |
| `MCP_HOT_RELOAD_DEBOUNCE` | `200` | Debounce delay in ms |

---

## Debouncing Logic

File editors often trigger multiple write events:

```
[0ms]   Change detected → start debounce timer
[5ms]   Change detected → reset timer
[10ms]  Change detected → reset timer
[210ms] Timer expires → process once
```

---

## Error Recovery

The watcher is designed to be resilient:

1. **Invalid YAML**: Keeps previous valid config
2. **Handler failure**: Continues with other handlers
3. **File deleted**: Logs and continues watching
4. **Permission errors**: Logs and continues

---

## Testing

### Unit Tests

```php
public function test_start_initializes_strategy(): void
{
    $factory = $this->createMock(WatchStrategyFactory::class);
    $strategy = $this->createMock(WatchStrategyInterface::class);

    $factory->expects($this->once())
        ->method('create')
        ->willReturn($strategy);

    $watcher = new ConfigWatcher(
        $factory,
        new ConfigDiffCalculator(),
        new ChangeHandlerRegistry(),
        $this->createMock(ConfigLoaderFactory::class),
    );

    $watcher->start('/path/to/context.yaml');

    $this->assertTrue($watcher->isWatching());
}

public function test_tick_debounces_rapid_changes(): void
{
    $strategy = $this->createMock(WatchStrategyInterface::class);
    $strategy->method('check')->willReturn(['/config.yaml']);

    $loaderFactory = $this->createMock(ConfigLoaderFactory::class);
    $loaderFactory->expects($this->once())->method('create'); // Only once!

    $watcher = $this->createWatcher($strategy, $loaderFactory);
    $watcher->start('/config.yaml');

    $watcher->tick(); // Start debounce
    $watcher->tick(); // Still debouncing

    usleep(250_000); // Wait for debounce

    $watcher->tick(); // Now processes
}

public function test_handles_config_load_error(): void
{
    $strategy = $this->createMock(WatchStrategyInterface::class);
    $strategy->method('check')->willReturn(['/config.yaml']);

    $loaderFactory = $this->createMock(ConfigLoaderFactory::class);
    $loaderFactory->method('create')
        ->willThrowException(new \RuntimeException('Parse error'));

    $watcher = $this->createWatcher($strategy, $loaderFactory);
    $watcher->start('/config.yaml');

    usleep(250_000);
    $watcher->tick();
    $watcher->tick();

    // Should not throw, still watching
    $this->assertTrue($watcher->isWatching());
}

public function test_disabled_watcher_does_nothing(): void
{
    $factory = $this->createMock(WatchStrategyFactory::class);
    $factory->expects($this->never())->method('create');

    $watcher = new ConfigWatcher(
        $factory,
        new ConfigDiffCalculator(),
        new ChangeHandlerRegistry(),
        $this->createMock(ConfigLoaderFactory::class),
        enabled: false,
    );

    $watcher->start('/config.yaml');

    $this->assertFalse($watcher->isWatching());
}
```

---

## Acceptance Criteria

- [ ] `ConfigWatcher::start()` initializes file watching
- [ ] `ConfigWatcher::tick()` is non-blocking (< 1ms)
- [ ] `ConfigWatcher::tick()` detects file changes
- [ ] Debouncing prevents multiple reloads
- [ ] Config load errors don't crash the watcher
- [ ] Handler errors don't stop other handlers
- [ ] `ConfigWatcher::stop()` releases all resources
- [ ] Disabled watcher consumes no resources
- [ ] Import file list updates dynamically
- [ ] Bootloader registers all components

---

## Notes

- `tick()` must complete in < 1ms for responsive server
- Consider adding reload statistics for monitoring
- The `ConfigLoaderFactory` allows testing with mocks
