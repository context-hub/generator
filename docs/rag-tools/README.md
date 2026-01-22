# Feature Request: Configurable RAG Knowledge Stores with Custom Tools

## Summary

Refactor the RAG module to support multiple named storage configurations (servers + collections) and enable defining
custom RAG tools via `context.yaml`. This improves AI understanding of tool purpose and allows project-specific
knowledge stores with meaningful names and descriptions.

## Problem Statement

### Current Issues

1. **Generic Tool Names**: Current tools (`rag-search`, `rag-store`) don't convey purpose to AI
2. **Single Storage**: Only one Qdrant endpoint/collection per project
3. **No Customization**: Tool descriptions are hardcoded, not project-specific
4. **Tight Coupling**: Storage configuration mixed with feature toggle
5. **CLI Limitations**: Commands don't support multi-collection operations

### User Pain Points

- AI doesn't understand when/why to use RAG tools
- Can't use multiple knowledge bases (e.g., docs + architecture + team conventions)
- Tool names like `rag-search` are meaningless to AI agents
- No way to separate different knowledge domains

---

## Proposed Solution

### New Configuration Schema

```yaml
# context.yaml

rag:
  # 1. Define RAG servers (connection settings)
  servers:
    default:
      driver: qdrant
      endpoint_url: ${RAG_QDRANT_ENDPOINT:-http://localhost:6333}
      api_key: ${RAG_QDRANT_API_KEY:-}  # optional

    cloud:
      driver: qdrant
      endpoint_url: https://my-cluster.qdrant.io
      api_key: ${QDRANT_CLOUD_API_KEY}

  # 2. Define named collections (reference servers)
  collections:
    project-docs:
      server: default                     # References server above
      collection: intruforce_docs         # Qdrant collection name
      description: "Project documentation and guides"
      # Optional per-collection overrides
      embeddings_dimension: 1536
      embeddings_distance: Cosine
      transformer:
        chunk_size: 1000
        overlap: 200

    architecture:
      server: default
      collection: intruforce_architecture
      description: "Architecture decisions and patterns"
      transformer:
        chunk_size: 2000                  # Larger chunks for architecture docs
        overlap: 400

    shared-knowledge:
      server: cloud
      collection: team_knowledge
      description: "Shared team knowledge base"

  # 3. Global vectorizer settings (shared by all collections)
  vectorizer:
    platform: openai
    model: text-embedding-3-small
    api_key: ${OPENAI_API_KEY}

  # 4. Default transformer settings (can be overridden per collection)
  transformer:
    chunk_size: 1000
    overlap: 200

# 5. Define custom RAG tools
tools:
  - id: project-docs-search
    name: find-docs                       # Optional: custom tool name (defaults to id)
    type: rag
    description: "Search project documentation for guides, tutorials, and how-to articles. Use this when you need to understand how to use project features."
    collection: project-docs              # Reference to named collection
    operations: [ search ]                # Available: search, store

  - id: project-docs-store
    type: rag
    description: "Store project documentation and insights for future retrieval."
    collection: project-docs
    operations: [ store ]

  - id: architecture-search
    name: arch-search                     # Tool will be registered as "arch-search"
    type: rag
    description: "Search architecture decisions, patterns, and design rationale. Use when understanding why code is structured a certain way."
    collection: architecture
    operations: [ search ]

  - id: team-knowledge-search
    type: rag
    description: "Search shared team knowledge including coding conventions and best practices."
    collection: shared-knowledge
    operations: [ search ]

  - id: team-knowledge-store
    type: rag
    description: "Store shared team knowledge for the whole team to access."
    collection: shared-knowledge
    operations: [ store ]
```

### Tool Naming Convention

For each collection with operations `[search, store]`, generate tools:

- `{collection-id}-search` - Search tool
- `{collection-id}-store` - Store tool

### Generated Tool Schemas

**Search Tool Schema:**

```json
{
  "type": "object",
  "properties": {
    "query": {
      "type": "string",
      "description": "Search query in natural language"
    },
    "type": {
      "type": "string",
      "description": "Filter by type: architecture, api, testing, convention, tutorial, reference, general"
    },
    "sourcePath": {
      "type": "string",
      "description": "Filter by source path (exact or prefix match)"
    },
    "limit": {
      "type": "integer",
      "description": "Maximum number of results to return",
      "default": 10,
      "minimum": 1,
      "maximum": 50
    }
  },
  "required": [
    "query"
  ]
}
```

**Store Tool Schema:**

```json
{
  "type": "object",
  "properties": {
    "content": {
      "type": "string",
      "description": "Content to store in the knowledge base"
    },
    "type": {
      "type": "string",
      "description": "Type: architecture, api, testing, convention, tutorial, reference, general",
      "default": "general"
    },
    "sourcePath": {
      "type": "string",
      "description": "Source path (e.g., 'src/Auth/Service.php')"
    },
    "tags": {
      "type": "string",
      "description": "Tags (comma-separated)"
    }
  },
  "required": [
    "content"
  ]
}
```

---

## CLI Commands Update

All RAG commands will support `--collection` option:

```bash
# Index into specific collection
ctx rag:index docs --collection=project-docs

# Index into all collections (default behavior)
ctx rag:index docs

# Status for specific collection
ctx rag:status --collection=architecture

# Status for all collections
ctx rag:status

# Clear specific collection
ctx rag:clear --collection=project-docs

# Initialize specific collection
ctx rag:init --collection=shared-knowledge

# Reindex specific collection
ctx rag:reindex docs --collection=project-docs
```

---

## Backward Compatibility

### Legacy Format Support

The old format will continue to work and be internally converted:

```yaml
# LEGACY FORMAT (still supported)
rag:
  enabled: true
  store:
    driver: qdrant
    qdrant:
      endpoint_url: http://localhost:6333
      collection: ctx_knowledge

# Internally converts to:
# servers: { default: { driver: qdrant, endpoint_url: ... } }
# collections: { default: { server: default, collection: ctx_knowledge } }
```

### Detection Logic

```php
// In RagParserPlugin
if (isset($data['store'])) {
    // Legacy format - convert to new structure
    return $this->convertLegacyFormat($data);
}
// New format with servers/collections
return $this->parseNewFormat($data);
```

### Default Tools for Legacy Config

When using legacy format without custom tools defined, automatically register:

- `rag-search` (pointing to `default` collection)
- `rag-store` (pointing to `default` collection)

---

## Benefits

| Aspect               | Before                 | After                              |
|----------------------|------------------------|------------------------------------|
| Tool naming          | `rag-search` (generic) | `project-docs-search` (meaningful) |
| AI understanding     | Low                    | High (custom descriptions)         |
| Storage flexibility  | 1 collection           | Unlimited collections              |
| Configuration        | Hardcoded              | Declarative YAML                   |
| Team sharing         | Difficult              | Easy (multiple servers)            |
| Knowledge separation | None                   | By domain/purpose                  |

---

## Implementation Stages

See individual stage files for detailed implementation:

1. **Stage 1**: Configuration Infrastructure (ServerConfig, CollectionConfig)
2. **Stage 2**: Store Registry & Factory
3. **Stage 3**: Service Layer Updates (collection-aware services)
4. **Stage 4**: RAG Tool Type Support in Tool Parser
5. **Stage 5**: Dynamic Tool Generation
6. **Stage 6**: CLI Commands Update
7. **Stage 7**: Integration & Testing

---

## Open Questions

1. **Default Collection**: When no `--collection` specified in CLI, operate on all or require explicit choice?
    - **Decision**: Operate on all collections by default, with clear output per collection

2. **Tool Validation**: Fail at startup if referenced collection doesn't exist?
    - **Decision**: Yes, fail early with clear error message

3. **Collection Inheritance**: Should collections inherit from a "default" template?
    - **Decision**: Collections inherit global `transformer` settings unless overridden
