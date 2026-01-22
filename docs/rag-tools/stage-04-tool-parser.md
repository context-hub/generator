# Stage 4: RAG Tool Type Support

## Overview

Extend the tool infrastructure to recognize `type: rag` tools in configuration. Create a `RagToolConfig` DTO to hold
RAG-specific tool settings (collection, operations). Update the JSON schema and add validation.

## Files

**CREATE:**

- `rag/Tool/RagToolConfig.php` - DTO for RAG tool configuration
- `tests/src/Rag/Tool/RagToolConfigTest.php` - Unit tests

**MODIFY:**

- `src/Tool/Config/ToolDefinition.php` (ctx-mcp-server) - Add RAG tool type handling
- `json-schema.json` - Add RAG tool schema with collection and operations

## Code References

### ToolDefinition Extra Handling

```php
// src/Tool/Config/ToolDefinition.php:48-55 (ctx-mcp-server)
// Extract any extra configuration data (type-specific)
$extra = [];
$reservedKeys = ['id', 'description', 'type', 'commands', 'schema', 'env', 'workingDir'];
foreach ($config as $key => $value) {
    if (!\in_array($key, $reservedKeys, true)) {
        $extra[$key] = $value;
    }
}
```

### HTTP Tool Validation Pattern

```php
// src/Tool/Config/ToolDefinition.php:76-79 (ctx-mcp-server)
if ($type === 'http') {
    if (!isset($config['requests']) || !\is_array($config['requests']) || empty($config['requests'])) {
        throw new \InvalidArgumentException('HTTP tool must have a non-empty requests array');
    }
}
```

### JSON Schema Tool Type Enum

```json
// json-schema.json:505-512
"type": {
  "type": "string",
  "enum": [
    "run",
    "http"
  ],
  "description": "Type of tool (run = command execution, http = HTTP requests)",
  "default": "run"
}
```

## Implementation Details

### 1. RagToolConfig DTO

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

/**
 * Configuration for RAG-type tools defined in context.yaml
 */
final readonly class RagToolConfig
{
    public const OPERATION_SEARCH = 'search';
    public const OPERATION_STORE = 'store';

    /**
     * @param string $id Tool identifier
     * @param string $description Tool description for AI
     * @param string $collection Reference to named collection
     * @param string[] $operations Available operations (search, store)
     */
    public function __construct(
        public string $id,
        public string $description,
        public string $collection,
        public array $operations = [self::OPERATION_SEARCH, self::OPERATION_STORE],
    ) {
        $this->validateOperations($operations);
    }

    public static function fromArray(array $data): self
    {
        if (empty($data['id']) || !\is_string($data['id'])) {
            throw new \InvalidArgumentException('RAG tool must have a non-empty id');
        }

        if (empty($data['description']) || !\is_string($data['description'])) {
            throw new \InvalidArgumentException('RAG tool must have a non-empty description');
        }

        if (empty($data['collection']) || !\is_string($data['collection'])) {
            throw new \InvalidArgumentException('RAG tool must specify a collection');
        }

        $operations = $data['operations'] ?? [self::OPERATION_SEARCH, self::OPERATION_STORE];
        if (!\is_array($operations)) {
            throw new \InvalidArgumentException('RAG tool operations must be an array');
        }

        return new self(
            id: $data['id'],
            description: $data['description'],
            collection: $data['collection'],
            operations: $operations,
        );
    }

    public function hasSearch(): bool
    {
        return \in_array(self::OPERATION_SEARCH, $this->operations, true);
    }

    public function hasStore(): bool
    {
        return \in_array(self::OPERATION_STORE, $this->operations, true);
    }

    /**
     * Get the tool ID for search operation.
     */
    public function getSearchToolId(): string
    {
        return $this->id . '-search';
    }

    /**
     * Get the tool ID for store operation.
     */
    public function getStoreToolId(): string
    {
        return $this->id . '-store';
    }

    private function validateOperations(array $operations): void
    {
        $valid = [self::OPERATION_SEARCH, self::OPERATION_STORE];

        foreach ($operations as $op) {
            if (!\in_array($op, $valid, true)) {
                throw new \InvalidArgumentException(
                    \sprintf('Invalid RAG operation "%s". Valid: %s', $op, \implode(', ', $valid)),
                );
            }
        }

        if (empty($operations)) {
            throw new \InvalidArgumentException('RAG tool must have at least one operation');
        }
    }
}
```

### 2. Updated ToolDefinition (ctx-mcp-server)

Add RAG tool validation after HTTP validation:

```php
// Add to ToolDefinition::fromArray() after HTTP validation

// Handle 'rag' type specific validations
if ($type === 'rag') {
    if (!isset($config['collection']) || !\is_string($config['collection']) || $config['collection'] === '') {
        throw new \InvalidArgumentException('RAG tool must specify a collection');
    }

    // Validate operations if provided
    if (isset($config['operations'])) {
        if (!\is_array($config['operations'])) {
            throw new \InvalidArgumentException('RAG tool operations must be an array');
        }

        $validOps = ['search', 'store'];
        foreach ($config['operations'] as $op) {
            if (!\in_array($op, $validOps, true)) {
                throw new \InvalidArgumentException(
                    \sprintf('Invalid RAG operation "%s". Valid: %s', $op, \implode(', ', $validOps)),
                );
            }
        }
    }
}
```

Update the `$reservedKeys` array:

```php
$reservedKeys = ['id', 'description', 'type', 'commands', 'schema', 'env', 'workingDir', 'requests', 'collection', 'operations'];
```

### 3. JSON Schema Update

Update `definitions.tool.properties.type`:

```json
"type": {
  "type": "string",
  "enum": [
    "run",
    "http",
    "rag"
  ],
  "description": "Type of tool (run = command execution, http = HTTP requests, rag = RAG knowledge base)",
  "default": "run"
}
```

Add RAG-specific properties:

```json
"collection": {
  "type": "string",
  "description": "Reference to named RAG collection (for 'rag' type tools)"
},
"operations": {
  "type": "array",
  "description": "Available operations for RAG tool (for 'rag' type tools)",
  "items": {
    "type": "string",
    "enum": ["search", "store"]
  },
  "default": ["search", "store"]
}
```

Add conditional validation:

```json
{
  "if": {
    "properties": {
      "type": {
        "const": "rag"
      }
    }
  },
  "then": {
    "required": ["collection"]
  }
}
```

### 4. Example Configuration

After this stage, the following configuration will be valid:

```yaml
tools:
  # Search-only tool
  - id: project-docs
    type: rag
    description: "Search project documentation for guides and tutorials"
    collection: project-docs
    operations: [search]

  # Store-only tool  
  - id: save-docs
    type: rag
    description: "Store documentation and insights"
    collection: project-docs
    operations: [store]

  # Both operations (default)
  - id: team-knowledge
    type: rag
    description: "Search and store team knowledge"
    collection: shared-knowledge
    operations: [search, store]
```

## Definition of Done

- [ ] `RagToolConfig` DTO created with validation
- [ ] `RagToolConfig::fromArray()` parses configuration correctly
- [ ] `hasSearch()` and `hasStore()` methods work correctly
- [ ] `getSearchToolId()` and `getStoreToolId()` return correct IDs
- [ ] `ToolDefinition::fromArray()` validates RAG tool configuration
- [ ] RAG tools stored in `extra` array with `collection` and `operations` keys
- [ ] JSON schema updated with `type: rag` and related properties
- [ ] Validation fails if `collection` is missing for RAG tools
- [ ] Validation fails for invalid operations
- [ ] Unit tests cover all validation scenarios

## Dependencies

**Requires**: Stage 1 (Configuration Infrastructure)
**Enables**: Stage 5 (Dynamic Tool Generation)
