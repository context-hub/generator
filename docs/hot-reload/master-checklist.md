# Hot-Reload Feature: Master Checklist

## Overview

This document tracks the implementation of hot-reload capability for MCP Server configuration. The feature allows dynamic updates to tools, prompts, and resources without server restart.

**Target Package**: `ctx/mcp-server`
**Estimated Stages**: 6
**Dependencies**: None (uses existing infrastructure)

> 📋 **Full Feature Request**: See [feature-request.md](feature-request.md) for complete problem statement, technical design, and references.

---

## Implementation Stages

| Stage | Title | Status | Description |
|-------|-------|--------|-------------|
| 1 | [Registry Mutability](stage-1-registry-mutability.md) | ✅ Complete | Add remove/update/clear methods to registries |
| 2 | [File Watch Strategies](stage-2-file-watch-strategies.md) | ✅ Complete | Implement inotify and polling file watchers |
| 3 | [Config Diff Calculator](stage-3-config-diff-calculator.md) | ✅ Complete | Calculate differences between configurations |
| 4 | [Change Handlers](stage-4-change-handlers.md) | ✅ Complete | Handle changes for tools/prompts/resources |
| 5 | [Config Watcher](stage-5-config-watcher.md) | ✅ Complete | Main orchestrator with debouncing |
| 6 | [Transport Integration](stage-6-transport-integration.md) | ✅ Complete | Integrate watcher into event loop |

---

## Stage Dependencies

```
Stage 1: Registry Mutability
    ↓
Stage 2: File Watch Strategies ──┐
    ↓                            │
Stage 3: Config Diff Calculator  │
    ↓                            │
Stage 4: Change Handlers ←───────┘
    ↓
Stage 5: Config Watcher
    ↓
Stage 6: Transport Integration
```

---

## Files to Create

### Stage 1
- `src/Tool/ToolRegistryInterface.php` (modify)
- `src/Tool/ToolRegistry.php` (modify)
- `src/Prompt/PromptRegistryInterface.php` (modify)
- `src/Prompt/PromptRegistry.php` (modify)

### Stage 2
- `src/Watcher/Strategy/WatchStrategyInterface.php`
- `src/Watcher/Strategy/InotifyWatchStrategy.php`
- `src/Watcher/Strategy/PollingWatchStrategy.php`
- `src/Watcher/Strategy/WatchStrategyFactory.php`

### Stage 3
- `src/Watcher/Diff/ConfigDiff.php`
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
- `src/Transport/StdioTransport.php` (modify)
- `src/ServerRunner.php` (modify)

---

## Testing Milestones

- [ ] Stage 1: Unit tests for registry mutations
- [ ] Stage 2: Unit tests for file watch strategies
- [ ] Stage 3: Unit tests for diff calculation
- [ ] Stage 4: Unit tests for change handlers
- [ ] Stage 5: Integration test for full reload cycle
- [ ] Stage 6: E2E test with MCP Inspector

---

## Success Criteria

1. Tools/prompts update within 3 seconds of config save
2. No server crashes on invalid config
3. < 1% CPU overhead when idle (polling mode)
4. All connected MCP clients receive notifications
5. Works with imported config files

---

## Notes

- All changes should be in `ctx/mcp-server` package
- Main CTX project requires minimal changes (only import path exposure)
- Each stage should be independently testable
- Maintain backward compatibility (hot-reload can be disabled)
