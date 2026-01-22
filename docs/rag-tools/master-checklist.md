# Feature: Configurable RAG Knowledge Stores with Custom Tools

## Overview

Refactor the RAG module to support multiple named storage configurations and custom RAG tools via `context.yaml`.
Enables AI-friendly tool names, multiple knowledge bases, and project-specific configurations.

## Stage Dependencies

```
Stage 1 (Config Infrastructure)
    ↓
Stage 2 (Store Registry)
    ↓
Stage 3 (Service Layer) → Stage 4 (Tool Parser)
    ↓                         ↓
Stage 5 (Dynamic Tools) ←─────┘
    ↓
Stage 6 (CLI Commands)
    ↓
Stage 7 (Integration)
```

---

## Development Progress

### Stage 1: Configuration Infrastructure

**Goal**: Create new config classes for servers and collections

- [x] 1.1: Create `ServerConfig` class with driver, endpoint, apiKey, dimensions, distance
- [x] 1.2: Create `CollectionConfig` class with server reference, collection name, description, transformer overrides
- [x] 1.3: Update `RagConfig` to hold `servers`, `collections` arrays + global `vectorizer`, `transformer`
- [x] 1.4: Update `RagParserPlugin` to parse new format with legacy fallback
- [x] 1.5: Update JSON schema with new configuration structure
- [ ] 1.6: Write unit tests for config parsing (new + legacy formats)

**Notes**:
- Created `ServerConfig` and `CollectionConfig` as readonly DTOs
- `RagConfig::fromArray()` auto-detects legacy vs new format via presence of `store` key
- Legacy format internally converts to new structure with `default` server/collection
- Added `isLegacyFormat()`, `getServerForCollection()`, and collection helper methods

**Status**: Completed (tests pending)
**Completed**: 2025-01-22

---

### Stage 2: Store Registry & Factory

**Goal**: Manage multiple store instances by collection name

- [x] 2.1: Create `StoreRegistryInterface` with `getStore(collection)`, `hasCollection()`, `getCollectionConfig()`
- [x] 2.2: Create `StoreRegistry` implementation with lazy store creation
- [x] 2.3: Update `StoreFactory` to accept `ServerConfig` + `CollectionConfig`
- [x] 2.4: Update `RagBootloader` to register `StoreRegistry` in container
- [ ] 2.5: Write tests for multi-store management

**Notes**:
- `StoreRegistry` lazily creates and caches stores per collection
- `StoreFactory::createForCollection()` added for new config structure
- Legacy `StoreFactory::create()` preserved for backward compatibility
- Updated `RagRegistry::jsonSerialize()` for new format

**Status**: Completed (tests pending)
**Completed**: 2025-01-22

---

### Stage 3: Service Layer Updates

**Goal**: Make IndexerService and RetrieverService collection-aware

- [x] 3.1: Create `ServiceFactory` for creating per-collection services
- [x] 3.2: Update `IndexerService` constructor to accept collection-specific transformer config
- [x] 3.3: Update `RetrieverService` constructor to accept collection-specific store
- [x] 3.4: Update `RagStoreHandler` to use collection from tool config
- [x] 3.5: Update `RagSearchHandler` to use collection from tool config
- [ ] 3.6: Write tests for collection-specific service creation

**Notes**:
- `ServiceFactory` creates and caches indexers/retrievers per collection
- Handlers now accept `ServiceFactory` + `collectionName` for flexibility
- `withCollection()` method added to handlers for creating collection-specific instances
- Legacy bindings in bootloader use first/default collection

**Status**: Completed (tests pending)
**Completed**: 2025-01-22

---

### Stage 4: RAG Tool Type Support

**Goal**: Add `type: rag` parsing to tool infrastructure

- [x] 4.1: Update `ToolDefinition::fromArray()` to recognize `type: rag`
- [x] 4.2: Create `RagToolConfig` DTO for RAG-specific tool configuration
- [ ] 4.3: Update JSON schema to include `type: rag` with `collection`, `operations` properties (skipped)
- [x] 4.4: Add validation: fail if referenced collection doesn't exist
- [ ] 4.5: Write tests for RAG tool parsing

**Notes**:
- `RagToolConfig` DTO with `fromArray()`, `hasSearch()`, `hasStore()` methods
- `RagToolRegistry` and `RagToolRegistryInterface` for managing RAG tool configs
- `RagToolParserPlugin` validates collection existence at parse time
- JSON schema update skipped per user request

**Status**: Completed (tests pending)
**Completed**: 2025-01-22

---

### Stage 5: Dynamic Tool Generation

**Goal**: Generate MCP tools from RAG tool configuration

- [x] 5.1: Create `DynamicRagSearchAction` that accepts collection config at construction
- [x] 5.2: Create `DynamicRagStoreAction` that accepts collection config at construction
- [x] 5.3: Create `RagToolFactory` to build actions from `RagToolConfig`
- [x] 5.4: Update `ActionsBootloader` to register dynamic RAG tools alongside static ones
- [x] 5.5: Implement tool schema generation based on operations (search/store)
- [ ] 5.6: Write integration tests for dynamic tool registration and execution

**Notes**:
- Dynamic actions use `ServiceFactory` for collection-specific services
- `RagToolFactory::createAll()` returns arrays of search and store actions
- `ActionsBootloader` registers dynamic tools via callback mechanism
- Static tools still registered when no custom tools defined

**Status**: Completed (tests pending)
**Completed**: 2025-01-22

---

### Stage 6: CLI Commands Update

**Goal**: Add `--collection` option to all RAG commands

- [x] 6.1: Update `RagIndexCommand` with `--collection` option, iterate all if not specified
- [x] 6.2: Update `RagStatusCommand` to show all collections or specific one
- [x] 6.3: Update `RagClearCommand` with `--collection` option
- [x] 6.4: Update `RagInitCommand` with `--collection` option
- [x] 6.5: Update `RagReindexCommand` with `--collection` option
- [x] 6.6: Add clear output formatting showing which collection is being operated on
- [ ] 6.7: Write tests for CLI commands with collection parameter

**Notes**:
- Created `CollectionAwareTrait` with `--collection` option and helper methods
- All commands iterate over collections with clear section headers
- `getTargetCollections()` returns specified or all collections
- Per-collection progress and statistics displayed

**Status**: Completed (tests pending)
**Completed**: 2025-01-22

---

### Stage 7: Integration & Testing

**Goal**: End-to-end testing and documentation

- [x] 7.1: Create example configuration files in `docs/example/config/`
- [ ] 7.2: Write integration tests with multi-collection setup
- [ ] 7.3: Test backward compatibility with legacy config format
- [ ] 7.4: Update RAG documentation in VitePress
- [ ] 7.5: Update developer reference guide
- [ ] 7.6: Manual testing of complete workflow

**Notes**:
- Created `rag-multi-collection.yaml` and `rag-custom-tools.yaml` examples
- Tests deferred per user request

**Status**: In Progress
**Completed**: Example configs created

---

## Codebase References

### Configuration Parsing

- `rag/RagParserPlugin.php` - Current RAG config parser (pattern to follow)
- `rag/Config/RagConfig.php` - Current config structure
- `rag/Config/StoreConfig.php` - Current store config
- `src/McpServer/Tool/ToolParserPlugin.php` (ctx-mcp-server) - Tool parsing pattern

### Store & Services

- `rag/Store/StoreFactory.php` - Current store creation
- `rag/Service/IndexerService.php` - Document indexing
- `rag/Service/RetrieverService.php` - Document retrieval
- `rag/RagBootloader.php` - Dependency injection setup

### Tool Infrastructure (ctx-mcp-server)

- `src/Tool/Config/ToolDefinition.php` - Tool config structure
- `src/Tool/ToolRegistry.php` - Tool registration
- `src/Tool/Types/` - Tool handler implementations

### CLI Commands

- `rag/Console/RagIndexCommand.php` - Index command pattern
- `rag/Console/RagStatusCommand.php` - Status command pattern

### MCP Actions

- `rag/MCP/Tools/RagSearch/RagSearchAction.php` - Search tool pattern
- `rag/MCP/Tools/RagStore/RagStoreAction.php` - Store tool pattern

---

## Usage Instructions

⚠️ **Keep this checklist updated:**

1. Mark completed substeps immediately with `[x]`
2. Add notes about deviations or challenges after each stage
3. Document decisions differing from plan
4. Update status when starting/completing stages
5. Record completion dates

**Status Values:**

- `Not Started` - Work hasn't begun
- `In Progress` - Currently being implemented
- `Blocked` - Waiting on something
- `Completed` - All substeps done and verified
