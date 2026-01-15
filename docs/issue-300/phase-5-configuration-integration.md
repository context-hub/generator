# Phase 5: Bootloader & Integration

## Objective

Create RagBootloader following the ExcludeBootloader pattern, integrate with ActionsBootloader for MCP tools, and add JSON schema validation.

---

## Pattern Reference

Following `ExcludeBootloader`:

```php
// ExcludeBootloader registers:
// 1. Singletons for registry interface
// 2. Parser plugin via ConfigLoaderBootloader

#[Singleton]
final class ExcludeBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            ExcludeRegistryInterface::class => ExcludeRegistry::class,
        ];
    }

    public function boot(ConfigLoaderBootloader $configLoader, ExcludeParserPlugin $excludeParser): void
    {
        $configLoader->registerParserPlugin($excludeParser);
    }
}
```

---

## Files to Create/Modify

### 5.1 RagBootloader

#### `rag/RagBootloader.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Butschster\ContextGenerator\Rag\Loader\FileSystemLoader;
use Butschster\ContextGenerator\Rag\Service\IndexerService;
use Butschster\ContextGenerator\Rag\Service\RetrieverService;
use Butschster\ContextGenerator\Rag\Store\StoreFactory;
use Butschster\ContextGenerator\Rag\Vectorizer\VectorizerFactory;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Core\Attribute\Singleton;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\StoreInterface;

#[Singleton]
final class RagBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [
            ConsoleBootloader::class,
        ];
    }
    
    public function defineSingletons(): array
    {
        return [
            // Registry
            RagRegistryInterface::class => RagRegistry::class,
            
            // Factories
            StoreFactory::class => StoreFactory::class,
            VectorizerFactory::class => VectorizerFactory::class,
            MetadataFactory::class => MetadataFactory::class,
            
            // Symfony AI Store components (created from registry config)
            StoreInterface::class => static function (
                StoreFactory $factory,
                RagRegistryInterface $registry,
            ): StoreInterface {
                return $factory->create($registry->getConfig());
            },
            
            Vectorizer::class => static function (
                VectorizerFactory $factory,
                RagRegistryInterface $registry,
            ): Vectorizer {
                return $factory->create($registry->getConfig());
            },
            
            // Provide RagConfig from registry for convenience
            RagConfig::class => static fn(RagRegistryInterface $registry): RagConfig 
                => $registry->getConfig(),
            
            // Services
            IndexerService::class => IndexerService::class,
            RetrieverService::class => RetrieverService::class,
            
            // Loader
            FileSystemLoader::class => FileSystemLoader::class,
        ];
    }
    
    public function boot(
        ConfigLoaderBootloader $configLoader,
        RagParserPlugin $ragParser,
        ConsoleBootloader $console,
        RagRegistryInterface $registry,
    ): void {
        // Register parser plugin (like ExcludeBootloader)
        $configLoader->registerParserPlugin($ragParser);
        
        // Register CLI commands only if RAG is enabled
        // Note: Commands will check isEnabled() at runtime since config may not be parsed yet
        $console->addCommand(Console\RagIndexCommand::class);
        $console->addCommand(Console\RagClearCommand::class);
        $console->addCommand(Console\RagReindexCommand::class);
        $console->addCommand(Console\RagStatusCommand::class);
    }
}
```

### 5.2 Modify ActionsBootloader for MCP Tools

#### Modify: `src/McpServer/ActionsBootloader.php`

Add imports:

```php
use Butschster\ContextGenerator\Rag\MCP\Tools\RagManage\RagManageAction;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\RagSearchAction;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\RagStoreAction;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
```

Add to `defineDependencies()`:

```php
use Butschster\ContextGenerator\Rag\RagBootloader;

public function defineDependencies(): array
{
    return [
        // ... existing dependencies
        RagBootloader::class,
    ];
}
```

Add to `actions()` method (or wherever tools are collected):

```php
// RAG Tools - only if enabled in context.yaml
if ($this->container->has(RagRegistryInterface::class)) {
    $ragRegistry = $this->container->get(RagRegistryInterface::class);
    if ($ragRegistry->isEnabled()) {
        $actions = [
            ...$actions,
            RagStoreAction::class,
            RagSearchAction::class,
            RagManageAction::class,
        ];
    }
}
```

### 5.3 JSON Schema Update

Add to `json-schema.json`:

```json
{
  "properties": {
    "rag": {
      "type": "object",
      "description": "RAG knowledge store configuration",
      "properties": {
        "enabled": {
          "type": "boolean",
          "default": false,
          "description": "Enable RAG knowledge store"
        },
        "store": {
          "type": "object",
          "description": "Vector store configuration",
          "properties": {
            "driver": {
              "type": "string",
              "enum": ["qdrant", "chromadb", "memory", "pinecone"],
              "default": "qdrant",
              "description": "Store backend driver"
            },
            "qdrant": {
              "type": "object",
              "properties": {
                "host": { 
                  "type": "string", 
                  "default": "localhost",
                  "description": "Qdrant server host"
                },
                "port": { 
                  "type": "integer", 
                  "default": 6333,
                  "description": "Qdrant server port"
                },
                "collection": { 
                  "type": "string", 
                  "default": "ctx_knowledge",
                  "description": "Collection name"
                }
              }
            }
          }
        },
        "vectorizer": {
          "type": "object",
          "description": "Embedding/vectorizer configuration",
          "properties": {
            "platform": {
              "type": "string",
              "enum": ["openai", "ollama", "azure"],
              "default": "openai",
              "description": "Embedding platform"
            },
            "model": {
              "type": "string",
              "default": "text-embedding-3-small",
              "description": "Embedding model name"
            }
          }
        },
        "transformer": {
          "type": "object",
          "description": "Text chunking configuration",
          "properties": {
            "chunk_size": { 
              "type": "integer", 
              "default": 1000,
              "description": "Maximum chunk size in characters"
            },
            "overlap": { 
              "type": "integer", 
              "default": 200,
              "description": "Overlap between chunks"
            }
          }
        }
      }
    }
  }
}
```

### 5.4 Register Bootloader in App

Add `RagBootloader` to the application bootloader list (typically in `app.php` or a kernel file):

```php
use Butschster\ContextGenerator\Rag\RagBootloader;

// In bootloaders array:
RagBootloader::class,
```

---

## Example context.yaml

```yaml
$schema: 'https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json'

import:
  - path: src/**/context.yaml

exclude:
  patterns:
    - ".env*"
  paths:
    - ".claude"

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

documents:
  - description: Project structure
    outputPath: structure.md
    sources:
      - type: tree
        sourcePaths: [src]
```

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `OPENAI_API_KEY` | - | OpenAI API key for embeddings |
| `OLLAMA_BASE_URL` | `http://localhost:11434` | Ollama server URL |
| `RAG_QDRANT_HOST` | `localhost` | Qdrant host |
| `RAG_QDRANT_PORT` | `6333` | Qdrant port |
| `RAG_COLLECTION` | `ctx_knowledge` | Qdrant collection name |

---

## Implementation Order

1. Create `RagBootloader`
2. Update JSON schema
3. Modify `ActionsBootloader` to include RAG tools
4. Register bootloader in app
5. Test integration

---

## Test Cases

### Integration Tests: `tests/src/Feature/Rag/`

```
RagIntegrationTest.php
- test_rag_registry_available_when_bootloader_loaded
- test_rag_disabled_by_default
- test_rag_enabled_when_config_present
- test_tools_registered_when_enabled
- test_commands_available

RagParserPluginIntegrationTest.php
- test_parses_rag_from_context_yaml
- test_config_available_in_registry_after_parse
```

---

## Definition of Done

- [ ] RagBootloader follows ExcludeBootloader pattern
- [ ] Parser plugin registered via ConfigLoaderBootloader
- [ ] MCP tools registered when `rag.enabled: true`
- [ ] CLI commands available
- [ ] JSON schema validates rag config
- [ ] Integration tests pass

---

## Estimated Effort

| Task                    | Complexity | Time   |
|-------------------------|------------|--------|
| RagBootloader           | Medium     | 2h     |
| ActionsBootloader mod   | Low        | 1h     |
| JSON schema update      | Low        | 0.5h   |
| App registration        | Low        | 0.5h   |
| Integration tests       | Medium     | 2h     |
| **Total**               |            | **~6h** |
