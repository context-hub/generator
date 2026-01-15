# RAG Knowledge Store - Implementation Master Checklist

## Overview

RAG (Retrieval-Augmented Generation) system for CTX using **Symfony AI Store** component.

**Pattern:** Follows `Exclude` config parser pattern  
**Namespace:** `Butschster\ContextGenerator\Rag` (autoload: `rag/`)  
**Config:** `context.yaml` → `rag` section  
**Dependencies:** `symfony/ai-store`, `symfony/ai-platform`, `symfony/ai-qdrant-store`

---

## Phase 1: Core Infrastructure & Config Parsing

> Config DTOs, parser plugin, registry, factories (following Exclude pattern)

- [ ] **1.1** Create config DTOs
    - [ ] `rag/Config/RagConfig.php`
    - [ ] `rag/Config/StoreConfig.php`
    - [ ] `rag/Config/VectorizerConfig.php`
    - [ ] `rag/Config/TransformerConfig.php`

- [ ] **1.2** Create registry (like ExcludeRegistry)
    - [ ] `rag/RagRegistryInterface.php`
    - [ ] `rag/RagRegistry.php`

- [ ] **1.3** Create parser plugin (like ExcludeParserPlugin)
    - [ ] `rag/RagParserPlugin.php`

- [ ] **1.4** Create document helpers
    - [ ] `rag/Document/DocumentType.php` - Enum
    - [ ] `rag/Document/MetadataFactory.php`

- [ ] **1.5** Create factories
    - [ ] `rag/Store/StoreFactory.php` - Creates Symfony AI Store
    - [ ] `rag/Vectorizer/VectorizerFactory.php` - Creates Vectorizer

**Definition of Done:** Parser plugin parses 'rag' section, factories create Symfony AI components

---

## Phase 2: Indexer & Retriever Services

> Wrap Symfony AI Store's Indexer and Retriever with CTX defaults

- [ ] **2.1** Create indexing service
    - [ ] `rag/Service/IndexResult.php`
    - [ ] `rag/Service/IndexerService.php`

- [ ] **2.2** Create retrieval service
    - [ ] `rag/Service/SearchResultItem.php`
    - [ ] `rag/Service/RetrieverService.php`

- [ ] **2.3** Create document loader
    - [ ] `rag/Loader/FileSystemLoader.php`

**Definition of Done:** Services wrap Symfony AI Store, loader scans directories

---

## Phase 3: MCP Tools

> Three MCP tools using services from Phase 2

- [ ] **3.1** rag-store tool
    - [ ] `rag/MCP/Tools/RagStore/Dto/RagStoreRequest.php`
    - [ ] `rag/MCP/Tools/RagStore/RagStoreHandler.php`
    - [ ] `rag/MCP/Tools/RagStore/RagStoreAction.php`

- [ ] **3.2** rag-search tool
    - [ ] `rag/MCP/Tools/RagSearch/Dto/RagSearchRequest.php`
    - [ ] `rag/MCP/Tools/RagSearch/RagSearchHandler.php`
    - [ ] `rag/MCP/Tools/RagSearch/RagSearchAction.php`

- [ ] **3.3** rag-manage tool
    - [ ] `rag/MCP/Tools/RagManage/Dto/RagManageRequest.php`
    - [ ] `rag/MCP/Tools/RagManage/RagManageHandler.php`
    - [ ] `rag/MCP/Tools/RagManage/RagManageAction.php`

**Definition of Done:** Tools work via MCP protocol

---

## Phase 4: CLI Commands

> Console commands for bulk operations

- [ ] **4.1** `rag:index` command
    - [ ] `rag/Console/RagIndexCommand.php`

- [ ] **4.2** `rag:clear` command
    - [ ] `rag/Console/RagClearCommand.php`

- [ ] **4.3** `rag:reindex` command
    - [ ] `rag/Console/RagReindexCommand.php`

- [ ] **4.4** `rag:status` command
    - [ ] `rag/Console/RagStatusCommand.php`

**Definition of Done:** Commands work from CLI with progress output

---

## Phase 5: Bootloader & Integration

> Bootloader (like ExcludeBootloader), ActionsBootloader integration

- [ ] **5.1** Create bootloader
    - [ ] `rag/RagBootloader.php` - Registers plugin, defines singletons

- [ ] **5.2** Update JSON schema
    - [ ] Add `rag` section to `json-schema.json`

- [ ] **5.3** Modify ActionsBootloader
    - [ ] Add RagBootloader dependency
    - [ ] Register RAG tools when enabled

- [ ] **5.4** Register in app
    - [ ] Add RagBootloader to bootloader list

**Definition of Done:** RAG enabled/disabled via context.yaml, tools auto-registered

---

## File Structure

```
rag/                                    # Autoload: Butschster\ContextGenerator\Rag
├── Config/
│   ├── RagConfig.php
│   ├── StoreConfig.php
│   ├── TransformerConfig.php
│   └── VectorizerConfig.php
├── Console/
│   ├── RagClearCommand.php
│   ├── RagIndexCommand.php
│   ├── RagReindexCommand.php
│   └── RagStatusCommand.php
├── Document/
│   ├── DocumentType.php
│   └── MetadataFactory.php
├── Loader/
│   └── FileSystemLoader.php
├── MCP/Tools/
│   ├── RagManage/
│   │   ├── Dto/RagManageRequest.php
│   │   ├── RagManageAction.php
│   │   └── RagManageHandler.php
│   ├── RagSearch/
│   │   ├── Dto/RagSearchRequest.php
│   │   ├── RagSearchAction.php
│   │   └── RagSearchHandler.php
│   └── RagStore/
│       ├── Dto/RagStoreRequest.php
│       ├── RagStoreAction.php
│       └── RagStoreHandler.php
├── Service/
│   ├── IndexerService.php
│   ├── IndexResult.php
│   ├── RetrieverService.php
│   └── SearchResultItem.php
├── Store/
│   └── StoreFactory.php
├── Vectorizer/
│   └── VectorizerFactory.php
├── RagBootloader.php
├── RagParserPlugin.php
├── RagRegistry.php
└── RagRegistryInterface.php
```

---

## Dependencies

**Already in composer.json:**

- `symfony/ai-store` ^0.2.0
- `symfony/ai-platform` ^0.2.0
- `symfony/ai-qdrant-store` ^0.3

---

## Testing Strategy

| Phase | Test Type   | Location                            |
|-------|-------------|-------------------------------------|
| 1     | Unit        | `tests/src/Unit/Rag/`               |
| 2     | Unit        | `tests/src/Unit/Rag/Service/`       |
| 3     | Integration | `tests/src/McpInspector/Tools/Rag/` |
| 4     | Feature     | `tests/src/Feature/Console/Rag/`    |
| 5     | Integration | `tests/src/Feature/Rag/`            |

---

## Progress Tracking

| Phase | Status        | Est. Time |
|-------|---------------|-----------|
| 1     | ⬜ Not Started | ~8h       |
| 2     | ⬜ Not Started | ~8h       |
| 3     | ⬜ Not Started | ~7h       |
| 4     | ⬜ Not Started | ~8h       |
| 5     | ⬜ Not Started | ~6h       |
| **Total** |           | **~37h**  |

**Legend:** ⬜ Not Started | 🟡 In Progress | ✅ Completed
