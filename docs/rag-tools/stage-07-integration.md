# Stage 7: Integration & Testing

## Overview

Final integration stage focusing on end-to-end testing, documentation updates, and example configurations. Verify
backward compatibility and complete the feature implementation.

## Files

**CREATE:**

- `docs/example/config/rag-multi-collection.yaml` - Example multi-collection config
- `docs/example/config/rag-custom-tools.yaml` - Example with custom RAG tools
- `tests/src/Feature/Rag/MultiCollectionIntegrationTest.php` - E2E tests
- `tests/src/Feature/Rag/LegacyConfigCompatibilityTest.php` - Backward compat tests

**MODIFY:**

- `docs/rag-developer-reference.md` - Update with new configuration
- `docs/example/config/rag-config.yaml` - Update with new options

## Code References

### Existing Example Config Pattern

```yaml
# docs/example/config/rag-config.yaml:17-38
rag:
  enabled: true
  store:
    driver: qdrant
    qdrant:
      endpoint_url: ${RAG_QDRANT_URL:-http://localhost:6333}
      # ...
```

### Feature Test Pattern

```php
// tests/src/Feature/Console/GenerateCommand/ToolsTest.php:33-50
$configFile = $this->createTempFile(<<<'YAML'
    tools:
      - id: test-command
        description: "A test tool"
        # ...
    YAML
);

$this->assertCommandOutput(/* ... */);
```

## Implementation Details

### 1. Example: Multi-Collection Configuration

```yaml
# docs/example/config/rag-multi-collection.yaml

# Multi-Collection RAG Configuration Example
#
# This example demonstrates how to configure multiple RAG collections
# for different knowledge domains within a project.
#
# Use cases:
# - Separate documentation from architecture knowledge
# - Keep team conventions in a shared cloud collection
# - Different chunk sizes for different content types

rag:
  # Define server connections
  servers:
    local:
      driver: qdrant
      endpoint_url: ${RAG_LOCAL_ENDPOINT:-http://localhost:6333}
      api_key: ${RAG_LOCAL_API_KEY:-}
      embeddings_dimension: 1536
      embeddings_distance: Cosine

    cloud:
      driver: qdrant
      endpoint_url: ${RAG_CLOUD_ENDPOINT}
      api_key: ${RAG_CLOUD_API_KEY}
      embeddings_dimension: 1536
      embeddings_distance: Cosine

  # Define named collections
  collections:
    project-docs:
      server: local
      collection: ${PROJECT_NAME:-myproject}_docs
      description: "Project documentation, guides, and tutorials"
      # Use default transformer settings

    architecture:
      server: local
      collection: ${PROJECT_NAME:-myproject}_architecture
      description: "Architecture decisions, patterns, and design rationale"
      transformer:
        chunk_size: 2000    # Larger chunks for architecture docs
        overlap: 400

    api-specs:
      server: local
      collection: ${PROJECT_NAME:-myproject}_api
      description: "API specifications and endpoint documentation"
      transformer:
        chunk_size: 500     # Smaller chunks for precise API matching
        overlap: 100

    team-knowledge:
      server: cloud
      collection: team_shared_knowledge
      description: "Shared team knowledge, conventions, and best practices"

  # Global vectorizer (shared by all collections)
  vectorizer:
    platform: openai
    model: text-embedding-3-small
    api_key: ${OPENAI_API_KEY}

  # Default transformer (used when collection doesn't override)
  transformer:
    chunk_size: 1000
    overlap: 200
```

### 2. Example: Custom RAG Tools Configuration

```yaml
# docs/example/config/rag-custom-tools.yaml

# Custom RAG Tools Example
#
# Define AI-friendly tools with meaningful names and descriptions
# that help the AI understand when and why to use each tool.

rag:
  servers:
    default:
      driver: qdrant
      endpoint_url: ${RAG_QDRANT_ENDPOINT:-http://localhost:6333}

  collections:
    project-docs:
      server: default
      collection: project_documentation

    architecture:
      server: default
      collection: architecture_decisions

    conventions:
      server: default
      collection: coding_conventions

# Custom RAG tools with meaningful names for AI
tools:
  # Documentation tools
  - id: find-documentation
    type: rag
    description: |
      Search project documentation for guides, tutorials, and how-to articles.
      Use this when you need to understand:
      - How to use a feature
      - Setup instructions
      - Configuration options
      - Best practices for using the project
    collection: project-docs
    operations: [search]

  - id: save-documentation
    type: rag
    description: |
      Store documentation, guides, or explanations about project features.
      Use this after discovering or documenting:
      - How a feature works
      - Setup procedures
      - Configuration guides
    collection: project-docs
    operations: [store]

  # Architecture tools
  - id: understand-architecture
    type: rag
    description: |
      Search architecture decisions and design patterns.
      Use this when you need to understand:
      - Why code is structured a certain way
      - Design decisions and their rationale
      - System boundaries and responsibilities
      - Integration patterns between components
    collection: architecture
    operations: [search]

  - id: document-architecture
    type: rag
    description: |
      Store architecture decisions, patterns, and design rationale.
      Use this after making or discovering:
      - Important design decisions
      - New patterns in the codebase
      - System integration approaches
    collection: architecture
    operations: [store]

  # Conventions tools
  - id: check-conventions
    type: rag
    description: |
      Search coding conventions and style guidelines.
      Use this BEFORE writing code to ensure:
      - Naming conventions are followed
      - Code style matches project standards
      - Patterns are consistent with existing code
    collection: conventions
    operations: [search]

  - id: record-convention
    type: rag
    description: |
      Store coding conventions and guidelines discovered in the codebase.
      Use this when you identify:
      - Naming patterns
      - Code organization rules
      - Testing conventions
      - Documentation standards
    collection: conventions
    operations: [store]
```

### 3. Integration Test: Multi-Collection

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Rag;

use Tests\Feature\TestCase;

final class MultiCollectionIntegrationTest extends TestCase
{
    public function testMultipleCollectionsCanBeConfigured(): void
    {
        $config = $this->createTempFile(<<<'YAML'
            rag:
              servers:
                default:
                  driver: memory
              collections:
                docs:
                  server: default
                  collection: test_docs
                arch:
                  server: default
                  collection: test_arch
              vectorizer:
                platform: openai
                model: text-embedding-3-small
                api_key: test-key
            YAML
        );

        $result = $this->runCommand('rag:status', ['--config-file' => $config]);

        $this->assertStringContainsString('docs', $result);
        $this->assertStringContainsString('arch', $result);
        $this->assertStringContainsString('test_docs', $result);
        $this->assertStringContainsString('test_arch', $result);
    }

    public function testIndexIntoSpecificCollection(): void
    {
        // Create config with multiple collections
        $config = $this->createMultiCollectionConfig();

        // Index into specific collection
        $result = $this->runCommand('rag:index', [
            'path' => 'docs',
            '--collection' => 'docs',
            '--config-file' => $config,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Indexing: docs', $result);
        $this->assertStringNotContainsString('Indexing: arch', $result);
    }

    public function testIndexIntoAllCollections(): void
    {
        $config = $this->createMultiCollectionConfig();

        $result = $this->runCommand('rag:index', [
            'path' => 'docs',
            '--config-file' => $config,
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Indexing: docs', $result);
        $this->assertStringContainsString('Indexing: arch', $result);
    }

    public function testInvalidCollectionNameFails(): void
    {
        $config = $this->createMultiCollectionConfig();

        $result = $this->runCommand('rag:status', [
            '--collection' => 'nonexistent',
            '--config-file' => $config,
        ]);

        $this->assertStringContainsString('Collection "nonexistent" not found', $result);
    }

    public function testCustomRagToolsAreRegistered(): void
    {
        $config = $this->createTempFile(<<<'YAML'
            rag:
              servers:
                default:
                  driver: memory
              collections:
                docs:
                  server: default
                  collection: test_docs
              vectorizer:
                platform: openai
                api_key: test-key
            tools:
              - id: find-docs
                type: rag
                description: Search documentation
                collection: docs
                operations: [search]
            YAML
        );

        $result = $this->runCommand('tool:list', ['--config-file' => $config]);

        $this->assertStringContainsString('find-docs', $result);
        $this->assertStringContainsString('Search documentation', $result);
    }

    private function createMultiCollectionConfig(): string
    {
        return $this->createTempFile(<<<'YAML'
            rag:
              servers:
                default:
                  driver: memory
              collections:
                docs:
                  server: default
                  collection: test_docs
                arch:
                  server: default
                  collection: test_arch
              vectorizer:
                platform: openai
                api_key: test-key
            YAML
        );
    }
}
```

### 4. Legacy Compatibility Test

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Rag;

use Tests\Feature\TestCase;

final class LegacyConfigCompatibilityTest extends TestCase
{
    public function testLegacyConfigStillWorks(): void
    {
        $config = $this->createTempFile(<<<'YAML'
            rag:
              enabled: true
              store:
                driver: memory
                memory:
                  collection: legacy_collection
              vectorizer:
                platform: openai
                model: text-embedding-3-small
                api_key: test-key
              transformer:
                chunk_size: 1000
                overlap: 200
            YAML
        );

        $result = $this->runCommand('rag:status', ['--config-file' => $config]);

        $this->assertStringContainsString('Enabled: Yes', $result);
        $this->assertStringContainsString('default', $result); // Converted collection name
    }

    public function testLegacyConfigWithDefaultTools(): void
    {
        $config = $this->createTempFile(<<<'YAML'
            rag:
              enabled: true
              store:
                driver: memory
              vectorizer:
                platform: openai
                api_key: test-key
            YAML
        );

        // When no custom RAG tools, static tools should be registered
        $result = $this->runCommand('tool:list', ['--config-file' => $config]);

        $this->assertStringContainsString('rag-search', $result);
        $this->assertStringContainsString('rag-store', $result);
    }

    public function testMixedLegacyAndNewConfig(): void
    {
        // This should fail - can't mix store and servers/collections
        $config = $this->createTempFile(<<<'YAML'
            rag:
              enabled: true
              store:
                driver: memory
              servers:
                default:
                  driver: memory
            YAML
        );

        $result = $this->runCommand('rag:status', ['--config-file' => $config]);

        // Should use legacy format when 'store' is present
        $this->assertStringContainsString('default', $result);
    }
}
```

### 5. Documentation Updates

Update `docs/rag-developer-reference.md` with:

1. **New Configuration Format**
    - Servers section explanation
    - Collections section explanation
    - Transformer overrides

2. **Custom RAG Tools**
    - How to define `type: rag` tools
    - Operations (search, store)
    - Tool naming conventions

3. **CLI Commands**
    - `--collection` option
    - Multi-collection behavior

4. **Migration Guide**
    - How to migrate from legacy config
    - Backward compatibility notes

## Definition of Done

- [ ] `rag-multi-collection.yaml` example created with comprehensive comments
- [ ] `rag-custom-tools.yaml` example created with AI-friendly descriptions
- [ ] Integration tests pass for multi-collection configuration
- [ ] Integration tests pass for custom RAG tools
- [ ] Legacy configuration format still works correctly
- [ ] Static RAG tools (rag-search, rag-store) used when no custom tools defined
- [ ] `docs/rag-developer-reference.md` updated with new features
- [ ] Existing example configs updated or supplemented
- [ ] All previous stage tests still pass
- [ ] Manual end-to-end testing completed:
    - [ ] Multi-collection indexing works
    - [ ] Custom tools appear in MCP tool list
    - [ ] CLI commands work with `--collection` option
    - [ ] Legacy config works without changes

## Dependencies

**Requires**: All previous stages (1-6)
**Enables**: Feature complete and ready for release
