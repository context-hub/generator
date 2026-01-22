# RAG Tools Feature - Developer Reference

Quick reference to existing code patterns, files, and line numbers for implementing the RAG Knowledge Store feature.

---

## Project Structure

```
Main Project (ctx/generator)
├── rag/                          # RAG module (modify)
│   ├── Config/                   # Configuration classes
│   ├── Console/                  # CLI commands
│   ├── MCP/Tools/                # MCP tool actions
│   ├── Service/                  # Business logic
│   └── Store/                    # Store factory

ctx-mcp-server Project
└── src/Tool/                     # Tool infrastructure (reference)
    ├── Config/                   # ToolDefinition, ToolSchema
    ├── Types/                    # Tool handlers
    └── ToolParserPlugin.php      # Tool parsing
```

---

## Configuration Classes

### Current RAG Config Structure

| File                               | Lines | Purpose                                                  |
|------------------------------------|-------|----------------------------------------------------------|
| `rag/Config/RagConfig.php`         | 1-27  | Main config with enabled, store, vectorizer, transformer |
| `rag/Config/StoreConfig.php`       | 1-32  | Store settings (driver, endpoint, collection)            |
| `rag/Config/VectorizerConfig.php`  | Full  | Vectorizer settings (platform, model, api_key)           |
| `rag/Config/TransformerConfig.php` | Full  | Chunking settings (chunk_size, overlap)                  |

### Config Parsing Pattern

```php
// rag/Config/StoreConfig.php:17-28 - fromArray() pattern
public static function fromArray(array $data): self
{
    $driver = (string) ($data['driver'] ?? 'qdrant');
    $driverConfig = $data[$driver] ?? [];

    return new self(
        driver: $driver,
        endpointUrl: (string) ($driverConfig['endpoint_url'] ?? 'http://localhost:6333'),
        // ...
    );
}
```

### RagParserPlugin Pattern

| File                      | Lines | Purpose                                          |
|---------------------------|-------|--------------------------------------------------|
| `rag/RagParserPlugin.php` | 20-30 | `getConfigKey()` returns 'rag'                   |
| `rag/RagParserPlugin.php` | 32-50 | `parse()` creates RagConfig and sets in registry |
| `rag/RagParserPlugin.php` | 45-47 | Logging pattern after parse                      |

```php
// rag/RagParserPlugin.php:32-48
public function parse(array $config, string $rootPath): ?RegistryInterface
{
    if (!$this->supports($config)) {
        return null;
    }

    $ragConfig = RagConfig::fromArray($config['rag']);
    $this->registry->setConfig($ragConfig);

    $this->logger?->info('Parsed RAG configuration', [
        'enabled' => $ragConfig->enabled,
        'store_driver' => $ragConfig->store->driver,
    ]);

    return $this->registry;
}
```

---

## Registry Pattern

### RagRegistry Implementation

| File                           | Lines | Purpose                         |
|--------------------------------|-------|---------------------------------|
| `rag/RagRegistry.php`          | 14-17 | Class definition with interface |
| `rag/RagRegistry.php`          | 21-34 | `setConfig()` and `isEnabled()` |
| `rag/RagRegistry.php`          | 36-45 | `getConfig()` with null check   |
| `rag/RagRegistryInterface.php` | Full  | Interface contract              |

```php
// rag/RagRegistry.php:17-45
final class RagRegistry implements RagRegistryInterface
{
    private ?RagConfig $config = null;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function setConfig(RagConfig $config): void
    {
        $this->config = $config;
        $this->logger?->debug('RAG config set', ['enabled' => $config->enabled]);
    }

    public function isEnabled(): bool
    {
        return $this->config?->enabled ?? false;
    }

    public function getConfig(): RagConfig
    {
        if ($this->config === null) {
            throw new \RuntimeException('RAG configuration not loaded');
        }
        return $this->config;
    }
}
```

---

## Store & Factory

### StoreFactory Pattern

| File                         | Lines | Purpose                           |
|------------------------------|-------|-----------------------------------|
| `rag/Store/StoreFactory.php` | 12-16 | Constructor with VariableResolver |
| `rag/Store/StoreFactory.php` | 18-35 | `create()` with match expression  |
| `rag/Store/StoreFactory.php` | 31-34 | Error handling for unknown driver |

```php
// rag/Store/StoreFactory.php:18-35
public function create(RagConfig $config): StoreInterface
{
    return match ($config->store->driver) {
        'qdrant' => $this->createQdrantStore($config),
        'memory', 'in_memory' => new InMemoryStore(),
        default => throw new \InvalidArgumentException(
            \sprintf('Unknown RAG store driver: %s', $config->store->driver),
        ),
    };
}
```

### Variable Resolution

```php
// rag/Store/StoreFactory.php - Variable resolution pattern
$endpointUrl = $this->variableResolver->resolve($config->store->endpointUrl);
$apiKey = $this->variableResolver->resolve($config->store->apiKey);
```

---

## Service Layer

### IndexerService

| File                             | Lines | Purpose                                  |
|----------------------------------|-------|------------------------------------------|
| `rag/Service/IndexerService.php` | 17-30 | Constructor creates TextSplitTransformer |
| `rag/Service/IndexerService.php` | 32-47 | `index()` single document                |
| `rag/Service/IndexerService.php` | 49-73 | `indexBatch()` multiple documents        |

```php
// rag/Service/IndexerService.php:17-30
public function __construct(
    private StoreInterface $store,
    private VectorizerInterface $vectorizer,
    private MetadataFactory $metadataFactory,
    RagConfig $config,
) {
    $this->transformer = new TextSplitTransformer(
        chunkSize: $config->transformer->chunkSize,
        overlap: $config->transformer->overlap,
    );
}
```

### RetrieverService

| File                               | Lines | Purpose                       |
|------------------------------------|-------|-------------------------------|
| `rag/Service/RetrieverService.php` | 13-19 | Constructor creates Retriever |
| `rag/Service/RetrieverService.php` | 21-45 | `search()` with filters       |
| `rag/Service/RetrieverService.php` | 47-68 | `buildFilter()` for Qdrant    |

### SearchResultItem

| File                               | Lines | Purpose                        |
|------------------------------------|-------|--------------------------------|
| `rag/Service/SearchResultItem.php` | 10-18 | Constructor with all fields    |
| `rag/Service/SearchResultItem.php` | 20-33 | `fromVectorDocument()` factory |
| `rag/Service/SearchResultItem.php` | 35-47 | `format()` for output          |

---

## MCP Tool Actions

### Static Tool Pattern

| File                                          | Lines | Purpose                                      |
|-----------------------------------------------|-------|----------------------------------------------|
| `rag/MCP/Tools/RagSearch/RagSearchAction.php` | 15-21 | Tool attribute with name, description, title |
| `rag/MCP/Tools/RagSearch/RagSearchAction.php` | 22    | InputSchema attribute                        |
| `rag/MCP/Tools/RagSearch/RagSearchAction.php` | 23-28 | Constructor with handler + logger            |
| `rag/MCP/Tools/RagSearch/RagSearchAction.php` | 30-42 | `__invoke()` method                          |

```php
// rag/MCP/Tools/RagSearch/RagSearchAction.php:15-42
#[Tool(
    name: 'rag-search',
    description: 'Search the project knowledge base using natural language.',
    title: 'RAG Search',
)]
#[InputSchema(class: RagSearchRequest::class)]
final readonly class RagSearchAction
{
    public function __construct(
        private RagSearchHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/rag-search', name: 'tools.rag-search')]
    public function __invoke(RagSearchRequest $request): CallToolResult
    {
        $this->logger->info('RAG Search', ['query' => $request->query]);

        try {
            return ToolResult::text($this->handler->handle($request));
        } catch (\Throwable $e) {
            $this->logger->error('RAG Search error', ['error' => $e->getMessage()]);
            return ToolResult::error($e->getMessage());
        }
    }
}
```

### Request DTO Pattern

| File                                               | Lines | Purpose                            |
|----------------------------------------------------|-------|------------------------------------|
| `rag/MCP/Tools/RagSearch/Dto/RagSearchRequest.php` | 10-22 | Request with Field attributes      |
| `rag/MCP/Tools/RagStore/Dto/RagStoreRequest.php`   | 10-28 | Store request with getParsedTags() |

```php
// rag/MCP/Tools/RagSearch/Dto/RagSearchRequest.php:10-22
final readonly class RagSearchRequest
{
    public function __construct(
        #[Field(description: 'Search query in natural language')]
        public string $query,
        #[Field(description: 'Filter by type: architecture, api, ...')]
        public ?string $type = null,
        #[Field(description: 'Filter by source path')]
        public ?string $sourcePath = null,
        #[Field(description: 'Maximum number of results')]
        #[Range(min: 1, max: 50)]
        public int $limit = 10,
    ) {}
}
```

### Handler Pattern

| File                                           | Lines | Purpose                           |
|------------------------------------------------|-------|-----------------------------------|
| `rag/MCP/Tools/RagSearch/RagSearchHandler.php` | 12-17 | Constructor with RetrieverService |
| `rag/MCP/Tools/RagSearch/RagSearchHandler.php` | 19-45 | `handle()` method                 |
| `rag/MCP/Tools/RagStore/RagStoreHandler.php`   | 12-17 | Constructor with IndexerService   |
| `rag/MCP/Tools/RagStore/RagStoreHandler.php`   | 19-38 | `handle()` method                 |

---

## Tool Infrastructure (ctx-mcp-server)

### ToolDefinition

| File (ctx-mcp-server)                | Lines  | Purpose                         |
|--------------------------------------|--------|---------------------------------|
| `src/Tool/Config/ToolDefinition.php` | 11-26  | Constructor with all properties |
| `src/Tool/Config/ToolDefinition.php` | 35-104 | `fromArray()` with validation   |
| `src/Tool/Config/ToolDefinition.php` | 48-55  | Extra fields extraction         |
| `src/Tool/Config/ToolDefinition.php` | 57-72  | Run type command parsing        |
| `src/Tool/Config/ToolDefinition.php` | 76-79  | HTTP type validation            |

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

### ToolParserPlugin

| File (ctx-mcp-server)           | Lines | Purpose                          |
|---------------------------------|-------|----------------------------------|
| `src/Tool/ToolParserPlugin.php` | 18-20 | `getConfigKey()` returns 'tools' |
| `src/Tool/ToolParserPlugin.php` | 22-56 | `parse()` iterates tools array   |
| `src/Tool/ToolParserPlugin.php` | 35-38 | Tool registration in registry    |

```php
// src/Tool/ToolParserPlugin.php:22-56 (ctx-mcp-server)
public function parse(array $config, string $rootPath): ?RegistryInterface
{
    if (!$this->supports($config)) {
        return null;
    }

    foreach ($config['tools'] as $index => $toolConfig) {
        try {
            $tool = ToolDefinition::fromArray($toolConfig);
            $this->toolRegistry->register($tool);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                \sprintf('Failed to parse tool at index %d: %s', $index, $e->getMessage()),
            );
        }
    }

    return $this->toolRegistry;
}
```

### ToolRegistry

| File (ctx-mcp-server)       | Lines | Purpose                      |
|-----------------------------|-------|------------------------------|
| `src/Tool/ToolRegistry.php` | 14-17 | Class with interfaces        |
| `src/Tool/ToolRegistry.php` | 19-23 | `register()` method          |
| `src/Tool/ToolRegistry.php` | 25-34 | `get()` with existence check |
| `src/Tool/ToolRegistry.php` | 36-44 | `has()` and `all()` methods  |

---

## CLI Commands

### Command Option Pattern

| File                              | Lines | Purpose                     |
|-----------------------------------|-------|-----------------------------|
| `rag/Console/RagIndexCommand.php` | 28-32 | Argument definition         |
| `rag/Console/RagIndexCommand.php` | 34-47 | Option definitions          |
| `rag/Console/RagIndexCommand.php` | 49-53 | Config path and env options |

```php
// rag/Console/RagIndexCommand.php:28-53
#[Argument(description: 'Directory path to index')]
protected string $path;

#[Option(shortcut: 'p', description: 'File pattern')]
protected string $pattern = '*.md';

#[Option(shortcut: 't', description: 'Document type')]
protected string $type = 'general';

#[Option(name: 'config-file', shortcut: 'c', description: 'Path to configuration file')]
protected ?string $configPath = null;

#[Option(name: 'env', shortcut: 'e', description: 'Path to .env file')]
protected ?string $envFile = null;
```

### Scope Pattern for Config Loading

| File                              | Lines | Purpose                             |
|-----------------------------------|-------|-------------------------------------|
| `rag/Console/RagIndexCommand.php` | 55-70 | Directories setup                   |
| `rag/Console/RagIndexCommand.php` | 72-95 | Container scope with config loading |
| `rag/Console/RagIndexCommand.php` | 80-90 | ConfigLoader creation               |

```php
// rag/Console/RagIndexCommand.php:55-95 - Scope pattern
$dirs = $dirs
    ->determineRootPath($this->configPath)
    ->withEnvFile($this->envFile);

return $container->runScope(
    bindings: new Scope(bindings: [DirectoriesInterface::class => $dirs]),
    scope: function (
        ConfigurationProvider $configProvider,
        RagRegistryInterface $registry,
        // ...
    ): int {
        // Load configuration
        try {
            $configLoader = $this->configPath !== null
                ? $configProvider->fromPath($this->configPath)
                : $configProvider->fromDefaultLocation();
            $configLoader->load();
        } catch (ConfigLoaderException $e) {
            $this->output->error($e->getMessage());
            return Command::FAILURE;
        }

        if (!$registry->isEnabled()) {
            $this->output->error('RAG is not enabled');
            return Command::FAILURE;
        }

        // ... command logic
    },
);
```

### Output Formatting

| File                               | Lines   | Purpose                     |
|------------------------------------|---------|-----------------------------|
| `rag/Console/RagStatusCommand.php` | 76-83   | Section and writeln pattern |
| `rag/Console/RagIndexCommand.php`  | 104-111 | Title and info display      |
| `rag/Console/RagIndexCommand.php`  | 130-145 | ProgressBar usage           |

```php
// Output patterns
$this->output->title('RAG Index');
$this->output->writeln(\sprintf('Path: <info>%s</info>', $this->path));
$this->output->section('Store Configuration');
$this->output->error('Error message');
$this->output->success('Success message');
$this->output->warning('Warning message');
$this->output->note('Note message');
```

---

## Bootloader Registration

### RagBootloader

| File                    | Lines | Purpose                     |
|-------------------------|-------|-----------------------------|
| `rag/RagBootloader.php` | 25-35 | `defineSingletons()` method |
| `rag/RagBootloader.php` | 37-55 | Service registrations       |

```php
// rag/RagBootloader.php - Registration pattern
$container->bindSingleton(
    RagRegistryInterface::class,
    static fn(LoggerInterface $logger) => new RagRegistry($logger),
);

$container->bindSingleton(
    StoreInterface::class,
    static function (RagRegistryInterface $registry, StoreFactory $factory) {
        return $factory->create($registry->getConfig());
    },
);
```

### ActionsBootloader RAG Registration

| File                                  | Lines   | Purpose                            |
|---------------------------------------|---------|------------------------------------|
| `src/McpServer/ActionsBootloader.php` | 251-256 | RAG tools conditional registration |

```php
// src/McpServer/ActionsBootloader.php:251-256
// RAG Tools - only if enabled in context.yaml
if ($ragRegistry->isEnabled()) {
    $actions[] = RagStoreAction::class;
    $actions[] = RagSearchAction::class;
    $actions[] = RagManageAction::class;
}
```

---

## JSON Schema

### Tool Schema Location

| File               | Lines   | Purpose                    |
|--------------------|---------|----------------------------|
| `json-schema.json` | 33-39   | Tools array definition     |
| `json-schema.json` | 490-569 | Tool definition            |
| `json-schema.json` | 505-512 | Tool type enum (run, http) |
| `json-schema.json` | 540-568 | Conditional validation     |

### RAG Schema Location

| File               | Lines   | Purpose                   |
|--------------------|---------|---------------------------|
| `json-schema.json` | 67-157  | RAG configuration         |
| `json-schema.json` | 76-117  | Store configuration       |
| `json-schema.json` | 119-138 | Vectorizer configuration  |
| `json-schema.json` | 140-155 | Transformer configuration |

---

## Test Patterns

### Feature Test Pattern

| File                                                      | Lines | Purpose              |
|-----------------------------------------------------------|-------|----------------------|
| `tests/src/Feature/Console/GenerateCommand/ToolsTest.php` | 33-50 | Config file creation |
| `tests/src/Feature/Console/GenerateCommand/ToolsTest.php` | 52-65 | Command assertion    |

```php
// tests/src/Feature/Console/GenerateCommand/ToolsTest.php
$configFile = $this->createTempFile(<<<'YAML'
    tools:
      - id: test-command
        description: "A test tool"
        type: run
        commands:
          - cmd: echo
            args: ["hello"]
    YAML
);

$this->assertCommandOutput(/* ... */);
```

---

## Key Files Summary

### Files to CREATE

| Stage | File                                    | Based On                                      |
|-------|-----------------------------------------|-----------------------------------------------|
| 1     | `rag/Config/ServerConfig.php`           | `rag/Config/StoreConfig.php`                  |
| 1     | `rag/Config/CollectionConfig.php`       | `rag/Config/StoreConfig.php`                  |
| 2     | `rag/Store/StoreRegistryInterface.php`  | `rag/RagRegistryInterface.php`                |
| 2     | `rag/Store/StoreRegistry.php`           | `rag/RagRegistry.php`                         |
| 3     | `rag/Service/ServiceFactory.php`        | `rag/Store/StoreFactory.php`                  |
| 4     | `rag/Tool/RagToolConfig.php`            | `src/Tool/Config/ToolDefinition.php`          |
| 5     | `rag/Tool/RagToolRegistryInterface.php` | `rag/RagRegistryInterface.php`                |
| 5     | `rag/Tool/RagToolRegistry.php`          | `src/Tool/ToolRegistry.php`                   |
| 5     | `rag/Tool/DynamicRagSearchAction.php`   | `rag/MCP/Tools/RagSearch/RagSearchAction.php` |
| 5     | `rag/Tool/DynamicRagStoreAction.php`    | `rag/MCP/Tools/RagStore/RagStoreAction.php`   |
| 5     | `rag/Tool/RagToolFactory.php`           | `rag/Store/StoreFactory.php`                  |
| 6     | `rag/Console/CollectionAwareTrait.php`  | New                                           |

### Files to MODIFY

| Stage | File                                              | Changes                         |
|-------|---------------------------------------------------|---------------------------------|
| 1     | `rag/Config/RagConfig.php`                        | Add servers, collections arrays |
| 1     | `rag/RagParserPlugin.php`                         | Legacy detection, new parsing   |
| 1     | `json-schema.json`                                | Add servers, collections schema |
| 2     | `rag/Store/StoreFactory.php`                      | Add `createForCollection()`     |
| 2     | `rag/RagBootloader.php`                           | Register StoreRegistry          |
| 3     | `rag/Service/IndexerService.php`                  | Accept TransformerConfig        |
| 3     | `rag/MCP/Tools/RagStore/RagStoreHandler.php`      | Use ServiceFactory              |
| 3     | `rag/MCP/Tools/RagSearch/RagSearchHandler.php`    | Use ServiceFactory              |
| 4     | `src/Tool/Config/ToolDefinition.php` (mcp-server) | Add RAG validation              |
| 5     | `src/McpServer/ActionsBootloader.php`             | Register dynamic tools          |
| 6     | `rag/Console/RagIndexCommand.php`                 | Add --collection                |
| 6     | `rag/Console/RagStatusCommand.php`                | Add --collection                |
| 6     | `rag/Console/RagClearCommand.php`                 | Add --collection                |
| 6     | `rag/Console/RagInitCommand.php`                  | Add --collection                |
| 6     | `rag/Console/RagReindexCommand.php`               | Add --collection                |
