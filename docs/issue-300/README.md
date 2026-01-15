# Feature Request: RAG Knowledge Store for CTX

## Summary

RAG (Retrieval-Augmented Generation) system for CTX using **Symfony AI Store** component.

**Pattern:** Follows existing `Exclude` config parser pattern  
**Namespace:** `Butschster\ContextGenerator\Rag`  
**Location:** `rag/` folder (separate autoload)  
**Config:** `context.yaml` → `rag` section

---

## Problem Statement

Every time an AI assistant starts working with a project, it must re-learn the codebase from scratch:

| Issue | Impact |
|-------|--------|
| Repetitive Analysis | Each session re-reads same files |
| Context Loss | Knowledge from previous sessions is lost |
| No Accumulated Documentation | Projects remain undocumented |
| Limited Context Window | Can't load entire project |

---

## Solution Overview

A knowledge system using **Symfony AI Store** that:

1. **Accumulates understanding** of code as you work
2. **Indexes documentation** in vector storage for semantic search
3. **Provides relevant context** on demand through MCP tools
4. **Supports bulk indexing** through CLI commands

---

## Key Features

### MCP Tools

| Tool | Purpose |
|------|---------|
| `rag-store` | Store documentation, insights, code explanations |
| `rag-search` | Semantic search across knowledge base |
| `rag-manage` | View statistics |

### CLI Commands

| Command | Purpose |
|---------|---------|
| `ctx rag:index` | Bulk index markdown/docs from directory |
| `ctx rag:clear` | Clear entries |
| `ctx rag:reindex` | Atomic clear + reindex |
| `ctx rag:status` | Display storage statistics |

---

## Architecture

Follows the existing `Exclude` config parser pattern:

```
ExcludeParserPlugin  → RagParserPlugin       (parses 'rag' section)
ExcludeRegistry      → RagRegistry           (stores config, provides access)
ExcludeBootloader    → RagBootloader         (registers plugin, DI)
```

Uses **Symfony AI Store** component (`symfony/ai-store`) as the foundation:

```
rag/                              # Namespace: Butschster\ContextGenerator\Rag
├── Config/                       # RagConfig, StoreConfig, etc.
├── Console/                      # CLI commands
├── Document/                     # DocumentType enum, MetadataFactory
├── Loader/                       # FileSystemLoader
├── MCP/Tools/                    # MCP tool actions
├── Service/                      # IndexerService, RetrieverService
├── Store/                        # StoreFactory
├── Vectorizer/                   # VectorizerFactory
├── RagBootloader.php
├── RagParserPlugin.php           # Like ExcludeParserPlugin
├── RagRegistry.php               # Like ExcludeRegistry
└── RagRegistryInterface.php
```

---

## Implementation Phases

| Phase | Description | Effort |
|-------|-------------|--------|
| **1** | Core Infrastructure (config DTOs, parser plugin, registry, factories) | ~8h |
| **2** | Indexer & Retriever Services | ~8h |
| **3** | MCP Tools | ~7h |
| **4** | CLI Commands | ~8h |
| **5** | Bootloader & Integration | ~6h |
| **Total** | | **~37h** |

---

## Configuration

```yaml
# context.yaml
rag:
  enabled: true
  
  store:
    driver: qdrant
    qdrant:
      host: ${RAG_QDRANT_HOST:-localhost}
      port: ${RAG_QDRANT_PORT:-6333}
      collection: ${RAG_COLLECTION:-ctx_knowledge}
    
  vectorizer:
    platform: openai
    model: text-embedding-3-small
    
  transformer:
    chunk_size: 1000
    overlap: 200
```

```bash
# Environment variables
OPENAI_API_KEY=sk-...
RAG_QDRANT_HOST=localhost
RAG_QDRANT_PORT=6333
```

---

## Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Pattern | Follow `Exclude` parser plugin | Consistent with codebase |
| Storage | Symfony AI Store | Production-ready, multiple backends |
| Store MVP | Qdrant | Already in composer.json, scalable |
| Namespace | `Butschster\ContextGenerator\Rag` | Separate autoload in `rag/` |
| Chunking | `TextSplitTransformer` | Built into Symfony AI Store |
| Embedding | `Vectorizer` interface | Supports OpenAI, Ollama, etc. |

---

## Dependencies

**Already in composer.json:**

- `symfony/ai-store` ^0.2.0
- `symfony/ai-platform` ^0.2.0
- `symfony/ai-qdrant-store` ^0.3

---

## Related Documents

### Implementation Phases

- [Phase 1: Core Infrastructure](./phase-1-core-infrastructure.md) — Config, parser plugin, registry, factories
- [Phase 2: Indexer & Retriever](./phase-2-chunking-embedding.md) — Service wrappers
- [Phase 3: MCP Tools](./phase-3-mcp-tools.md) — rag-store, rag-search, rag-manage
- [Phase 4: CLI Commands](./phase-4-cli-commands.md) — Bulk operations
- [Phase 5: Bootloader & Integration](./phase-5-configuration-integration.md) — DI, tool registration

### Tracking

- [Master Checklist](./master-checklist.md) — Progress tracking

---

## Success Criteria

- [ ] Follows existing `Exclude` config parser pattern
- [ ] AI can store and retrieve project knowledge
- [ ] Search returns relevant results ranked by relevance
- [ ] CLI can bulk-index existing documentation
- [ ] Configuration via context.yaml
- [ ] Uses Symfony AI Store (no custom storage)
