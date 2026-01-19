# RAG-Based Knowledge Store - Functional Requirements

## Overview

RAG system for CTX using **Symfony AI Store** component to accumulate, index, and retrieve knowledge about codebases.

---

## User Stories

### For Individual Developers

> "As a developer, I want the AI assistant to remember my project's context between sessions."

**Scenario**: Developer works on microservice architecture. In first session, AI studies the authorization service and
documents its API. In next session, AI can find that information via RAG search.

### For Teams

> "As a team lead, I want project knowledge to accumulate and be accessible to the entire team."

**Scenario**: New developer joins. Instead of weeks studying codebase, they get access to accumulated documentation that
AI generated.

### For AI Assistants

> "As an AI assistant, I want access to previously learned information about the project."

**Scenario**: When asked "how to write a test for the payment service," AI searches RAG for existing testing patterns
and generates code matching project style.

---

## Functional Requirements

### MCP Tools

#### Tool: `rag-store`

Store documentation and knowledge.

**Parameters:**

- `content` (required): Content to store
- `type`: Documentation type (architecture, api, testing, convention, general, tutorial, reference)
- `sourcePath`: Related source file path
- `tags`: Comma-separated tags

#### Tool: `rag-search`

Semantic search across knowledge base.

**Parameters:**

- `query` (required): Natural language search query
- `type`: Filter by documentation type
- `pathPrefix`: Filter by path prefix
- `limit`: Max results (default: 10)
- `minScore`: Minimum relevance score (default: 0.3)

#### Tool: `rag-manage`

Manage RAG entries.

**Parameters:**

- `action`: stats (more actions in future)

---

### CLI Commands

#### `ctx rag:index`

Bulk index documentation:

```bash
ctx rag:index ./docs --pattern="*.md" --recursive --type=api-docs
```

**Options:**

- `--pattern, -p`: File glob pattern (default: `*.md`)
- `--type, -t`: Documentation type tag
- `--recursive, -r`: Process subdirectories
- `--dry-run`: Preview without indexing

#### `ctx rag:clear`

Clear entries:

```bash
ctx rag:clear --force
```

#### `ctx rag:reindex`

Atomic reindex:

```bash
ctx rag:reindex ./docs --pattern="*.md" --recursive
```

#### `ctx rag:status`

View statistics:

```bash
ctx rag:status --json
```

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

---

## Integration Scenarios

### Scenario 1: Initial Documentation Index

```bash
# Index existing markdown docs
ctx rag:index ./docs --recursive --type=api-docs

# AI can now answer questions
```

### Scenario 2: Incremental Learning

```
User: "Study the payment module and document it"

AI:
1. Uses directory-list, file-read to study module
2. Forms understanding of architecture
3. Uses rag-store to save documentation
4. Returns summary to user
```

### Scenario 3: CI/CD Integration

```yaml
# .github/workflows/docs.yml
name: Update RAG Index

on:
  push:
    paths: [ 'docs/**' ]

jobs:
  reindex:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Reindex documentation
        run: ctx rag:reindex ./docs --type=api-docs
```

---

## Expected Benefits

### For AI Response Quality

| Without RAG                           | With RAG                               |
|---------------------------------------|----------------------------------------|
| "Write a test" → generic test         | "Write a test" → matches project style |
| "How does auth work?" → re-reads code | "How does auth work?" → instant answer |
| Each session starts from zero         | Accumulated context                    |

### For Development Teams

- **Onboarding** — new developers ramp up faster
- **Documentation** — created organically during work
- **Consistency** — single source of truth
- **Knowledge Preservation** — survives team changes
