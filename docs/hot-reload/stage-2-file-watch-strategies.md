# Stage 2: File Watch Strategies

## Objective

Implement file watching mechanisms to detect configuration file changes efficiently.

---

## Problem

The server needs to know when `context.yaml` or any imported config files change. Different platforms offer different capabilities:

- **Linux**: `inotify` kernel subsystem (most efficient)
- **macOS/Windows/Other**: Polling with `filemtime()` (universal fallback)

---

## Solution

Create a strategy pattern for file watching with automatic selection of the best available method.

---

## Files to Create

### Directory Structure

```
src/Watcher/
└── Strategy/
    ├── WatchStrategyInterface.php
    ├── InotifyWatchStrategy.php
    ├── PollingWatchStrategy.php
    └── WatchStrategyFactory.php
```

---

## Interface Definition

### `src/Watcher/Strategy/WatchStrategyInterface.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Strategy;

interface WatchStrategyInterface
{
    /**
     * Add a file to watch list
     */
    public function addFile(string $path): void;

    /**
     * Remove a file from watch list
     */
    public function removeFile(string $path): void;

    /**
     * Clear all watched files
     */
    public function clear(): void;

    /**
     * Check for changes (non-blocking)
     * Returns array of changed file paths, empty if no changes
     *
     * @return string[]
     */
    public function check(): array;

    /**
     * Release any resources
     */
    public function stop(): void;
}
```

---

## Implementations

### 1. `PollingWatchStrategy.php`

Universal fallback using `filemtime()`:

```php
final class PollingWatchStrategy implements WatchStrategyInterface
{
    /** @var array<string, int> path => last known mtime */
    private array $files = [];

    private float $lastCheckTime = 0;

    public function __construct(
        private readonly int $intervalMs = 2000,
    ) {}

    public function addFile(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $this->files[$path] = filemtime($path) ?: 0;
    }

    public function removeFile(string $path): void
    {
        unset($this->files[$path]);
    }

    public function clear(): void
    {
        $this->files = [];
    }

    public function check(): array
    {
        $now = microtime(true) * 1000;

        // Respect polling interval
        if ($now - $this->lastCheckTime < $this->intervalMs) {
            return [];
        }
        $this->lastCheckTime = $now;

        $changed = [];

        foreach ($this->files as $path => $lastMtime) {
            if (!file_exists($path)) {
                // File deleted - consider it changed
                $changed[] = $path;
                continue;
            }

            clearstatcache(true, $path);
            $currentMtime = filemtime($path) ?: 0;

            if ($currentMtime > $lastMtime) {
                $changed[] = $path;
                $this->files[$path] = $currentMtime;
            }
        }

        return $changed;
    }

    public function stop(): void
    {
        $this->clear();
    }
}
```

### 2. `InotifyWatchStrategy.php`

Linux-specific using `ext-inotify`:

```php
final class InotifyWatchStrategy implements WatchStrategyInterface
{
    /** @var resource|null */
    private $inotify = null;

    /** @var array<string, int> path => watch descriptor */
    private array $watches = [];

    /** @var array<int, string> watch descriptor => path */
    private array $descriptorToPath = [];

    public function __construct()
    {
        if (!extension_loaded('inotify')) {
            throw new \RuntimeException('ext-inotify is not available');
        }

        $this->inotify = inotify_init();

        // Make non-blocking
        stream_set_blocking($this->inotify, false);
    }

    public function addFile(string $path): void
    {
        if (!file_exists($path) || $this->inotify === null) {
            return;
        }

        if (isset($this->watches[$path])) {
            return; // Already watching
        }

        $wd = inotify_add_watch(
            $this->inotify,
            $path,
            IN_MODIFY | IN_CLOSE_WRITE | IN_DELETE_SELF | IN_MOVE_SELF
        );

        if ($wd !== false) {
            $this->watches[$path] = $wd;
            $this->descriptorToPath[$wd] = $path;
        }
    }

    public function removeFile(string $path): void
    {
        if (!isset($this->watches[$path]) || $this->inotify === null) {
            return;
        }

        $wd = $this->watches[$path];
        @inotify_rm_watch($this->inotify, $wd);

        unset($this->watches[$path]);
        unset($this->descriptorToPath[$wd]);
    }

    public function clear(): void
    {
        foreach ($this->watches as $path => $wd) {
            $this->removeFile($path);
        }
    }

    public function check(): array
    {
        if ($this->inotify === null) {
            return [];
        }

        $events = @inotify_read($this->inotify);

        if ($events === false) {
            return []; // No events (non-blocking)
        }

        $changed = [];

        foreach ($events as $event) {
            $wd = $event['wd'];
            if (isset($this->descriptorToPath[$wd])) {
                $changed[] = $this->descriptorToPath[$wd];
            }
        }

        return array_unique($changed);
    }

    public function stop(): void
    {
        $this->clear();

        if ($this->inotify !== null) {
            fclose($this->inotify);
            $this->inotify = null;
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
```

### 3. `WatchStrategyFactory.php`

Auto-selects best available strategy:

```php
final class WatchStrategyFactory
{
    public function __construct(
        private readonly int $pollingIntervalMs = 2000,
    ) {}

    public function create(): WatchStrategyInterface
    {
        // Prefer inotify on Linux if available
        if (extension_loaded('inotify')) {
            return new InotifyWatchStrategy();
        }

        // Fallback to polling
        return new PollingWatchStrategy($this->pollingIntervalMs);
    }

    public function createPolling(): PollingWatchStrategy
    {
        return new PollingWatchStrategy($this->pollingIntervalMs);
    }

    public function createInotify(): InotifyWatchStrategy
    {
        if (!extension_loaded('inotify')) {
            throw new \RuntimeException('ext-inotify is not available');
        }
        return new InotifyWatchStrategy();
    }
}
```

---

## Implementation Details

### inotify Events

| Event | Description |
|-------|-------------|
| `IN_MODIFY` | File content was modified |
| `IN_CLOSE_WRITE` | File opened for writing was closed |
| `IN_DELETE_SELF` | Watched file was deleted |
| `IN_MOVE_SELF` | Watched file was moved |

### Polling Considerations

- Default interval: 2000ms (2 seconds)
- Uses `clearstatcache()` to get fresh mtime
- Low overhead for typical config file count (< 10 files)

### Resource Management

- `InotifyWatchStrategy` properly cleans up watch descriptors
- `stop()` must be called on shutdown
- Destructor provides safety net

---

## Testing

### Unit Tests for PollingWatchStrategy

```php
public function test_detects_file_modification(): void
{
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'initial');

    $strategy = new PollingWatchStrategy(intervalMs: 0); // No delay for testing
    $strategy->addFile($tempFile);

    // No changes yet
    $this->assertEmpty($strategy->check());

    // Modify file
    sleep(1); // Ensure mtime changes
    file_put_contents($tempFile, 'modified');

    $changed = $strategy->check();

    $this->assertContains($tempFile, $changed);

    unlink($tempFile);
}

public function test_respects_polling_interval(): void
{
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'initial');

    $strategy = new PollingWatchStrategy(intervalMs: 5000);
    $strategy->addFile($tempFile);

    // First check should work
    $strategy->check();

    // Immediate second check should return empty (interval not passed)
    sleep(1);
    file_put_contents($tempFile, 'modified');
    $this->assertEmpty($strategy->check());

    unlink($tempFile);
}

public function test_detects_file_deletion(): void
{
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'content');

    $strategy = new PollingWatchStrategy(intervalMs: 0);
    $strategy->addFile($tempFile);

    unlink($tempFile);

    $changed = $strategy->check();

    $this->assertContains($tempFile, $changed);
}
```

### Unit Tests for InotifyWatchStrategy

Skip if `ext-inotify` not available:

```php
protected function setUp(): void
{
    if (!extension_loaded('inotify')) {
        $this->markTestSkipped('ext-inotify not available');
    }
}

public function test_detects_file_modification(): void
{
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'initial');

    $strategy = new InotifyWatchStrategy();
    $strategy->addFile($tempFile);

    file_put_contents($tempFile, 'modified');

    // Small delay for inotify event
    usleep(50000);

    $changed = $strategy->check();

    $this->assertContains($tempFile, $changed);

    $strategy->stop();
    unlink($tempFile);
}
```

### Unit Tests for WatchStrategyFactory

```php
public function test_creates_strategy(): void
{
    $factory = new WatchStrategyFactory();
    $strategy = $factory->create();

    $this->assertInstanceOf(WatchStrategyInterface::class, $strategy);
}

public function test_creates_polling_explicitly(): void
{
    $factory = new WatchStrategyFactory(pollingIntervalMs: 1000);
    $strategy = $factory->createPolling();

    $this->assertInstanceOf(PollingWatchStrategy::class, $strategy);
}
```

---

## Acceptance Criteria

- [ ] `PollingWatchStrategy` detects file modifications
- [ ] `PollingWatchStrategy` respects polling interval
- [ ] `PollingWatchStrategy` handles file deletion
- [ ] `InotifyWatchStrategy` detects file modifications (if ext available)
- [ ] `InotifyWatchStrategy` properly releases resources
- [ ] `WatchStrategyFactory` auto-selects best strategy
- [ ] All strategies implement same interface

---

## Notes

- `ext-inotify` installation: `pecl install inotify`
- On Docker, may need to install: `apt-get install php-inotify`
- macOS alternative: `fsevents` (not implemented in this stage)
- Consider adding strategy name for logging: `getName(): string`
