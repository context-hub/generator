# RAG System - Developer Reference

## Overview

The RAG (Retrieval-Augmented Generation) system provides vector-based knowledge storage and retrieval. It follows the same plugin pattern as `ExcludeParserPlugin` for configuration parsing.

## Architecture

```
Butschster\ContextGenerator\Rag\
├── Config/
│   ├── RagConfig.php              # Main config DTO
│   ├── StoreConfig.php            # Vector store settings
│   ├── VectorizerConfig.php       # Embeddings settings
│   └── TransformerConfig.php      # Chunking settings
├── Console/
│   ├── RagStatusCommand.php       # ctx rag:status
│   ├── RagIndexCommand.php        # ctx rag:index
│   ├── RagClearCommand.php        # ctx rag:clear
│   └── RagReindexCommand.php      # ctx rag:reindex
├── Document/
│   ├── DocumentType.php           # Enum: architecture, api, etc.
│   └── MetadataFactory.php        # Creates document metadata
├── Loader/
│   └── FileSystemLoader.php       # Loads files for indexing
├── MCP/Tools/
│   ├── RagStore/                  # rag-store tool
│   ├── RagSearch/                 # rag-search tool
│   └── RagManage/                 # rag-manage tool
├── Service/
│   ├── IndexerService.php         # Wraps Symfony Indexer
│   ├── RetrieverService.php       # Wraps Symfony Retriever
│   ├── IndexResult.php            # Index operation result
│   └── SearchResultItem.php       # Search result DTO
├── Store/
│   └── StoreFactory.php           # Creates store instances
├── Vectorizer/
│   └── VectorizerFactory.php      # Creates vectorizer instances
├── RagBootloader.php              # DI registration
├── RagParserPlugin.php            # Config parser plugin
├── RagRegistry.php                # Holds parsed config
└── RagRegistryInterface.php       # Registry interface
```

## Dependencies

Uses Symfony AI components (already in composer.json):
- `symfony/ai-store` - Core store abstractions
- `symfony/ai-platform` - Platform integrations (OpenAI, etc.)
- `symfony/ai-qdrant-store` - Qdrant vector store

## Configuration DTOs

### RagConfig

**Location**: `rag/Config/RagConfig.php`

```php
final readonly class RagConfig
{
    public function __construct(
        public bool $enabled = false,
        public StoreConfig $store = new StoreConfig(),
        public VectorizerConfig $vectorizer = new VectorizerConfig(),
        public TransformerConfig $transformer = new TransformerConfig(),
    ) {}

    public static function fromArray(array $data): self;
}
```

### StoreConfig

**Location**: `rag/Config/StoreConfig.php`

```php
final readonly class StoreConfig
{
    public function __construct(
        public string $driver = 'qdrant',
        public string $endpointUrl = 'http://localhost:6333',
        public string $apiKey = '',
        public string $collection = 'ctx_knowledge',
        public int $embeddingsDimension = 1536,
        public string $embeddingsDistance = 'Cosine',
    ) {}

    public static function fromArray(array $data): self
    {
        $driver = (string) ($data['driver'] ?? 'qdrant');
        $driverConfig = $data[$driver] ?? [];

        return new self(
            driver: $driver,
            endpointUrl: (string) ($driverConfig['endpoint_url'] ?? '...'),
            // ... maps nested config
        );
    }
}
```

**Key point**: Config values can contain `${VAR}` placeholders. Resolution happens in factories.

### VectorizerConfig

**Location**: `rag/Config/VectorizerConfig.php`

```php
final readonly class VectorizerConfig
{
    public function __construct(
        public string $platform = 'openai',
        public string $model = 'text-embedding-3-small',
        public string $apiKey = '',
    ) {}
}
```

## Parser Plugin Pattern

### RagParserPlugin

**Location**: `rag/RagParserPlugin.php`

Follows the same pattern as `ExcludeParserPlugin`:

```php
final readonly class RagParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private RagRegistryInterface $registry,
    ) {}

    public function getConfigKey(): string
    {
        return 'rag';
    }

    public function supports(array $config): bool
    {
        return isset($config['rag']) && \is_array($config['rag']);
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        $ragConfig = RagConfig::fromArray($config['rag']);
        $this->registry->setConfig($ragConfig);
        return $this->registry;
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        return $config; // No modifications needed
    }
}
```

### RagRegistry

**Location**: `rag/RagRegistry.php`

```php
final class RagRegistry implements RagRegistryInterface, RegistryInterface
{
    private RagConfig $config;

    public function __construct()
    {
        $this->config = new RagConfig(); // Defaults
    }

    public function setConfig(RagConfig $config): void
    {
        $this->config = $config;
    }

    public function getConfig(): RagConfig
    {
        return $this->config;
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }
}
```

## Factories with Variable Resolution

### StoreFactory

**Location**: `rag/Store/StoreFactory.php`

```php
final readonly class StoreFactory
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    public function create(RagConfig $config): StoreInterface
    {
        return match ($config->store->driver) {
            'qdrant' => new QdrantStore(
                httpClient: HttpClient::create(),
                // Variables resolved here!
                endpointUrl: $this->variableResolver->resolve($config->store->endpointUrl),
                apiKey: $this->variableResolver->resolve($config->store->apiKey),
                collectionName: $this->variableResolver->resolve($config->store->collection),
                embeddingsDimension: $config->store->embeddingsDimension,
                embeddingsDistance: $config->store->embeddingsDistance,
            ),
            'memory', 'in_memory' => new InMemoryStore(),
            default => throw new \InvalidArgumentException("Unknown driver"),
        };
    }
}
```

### VectorizerFactory

**Location**: `rag/Vectorizer/VectorizerFactory.php`

```php
final readonly class VectorizerFactory
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    public function create(RagConfig $config): VectorizerInterface
    {
        $platform = match ($config->vectorizer->platform) {
            'openai' => OpenAiPlatformFactory::create(
                // Variable resolved here!
                apiKey: $this->variableResolver->resolve($config->vectorizer->apiKey),
            ),
            default => throw new \InvalidArgumentException("Unknown platform"),
        };

        return new Vectorizer($platform, $config->vectorizer->model);
    }
}
```

## Bootloader

**Location**: `rag/RagBootloader.php`

```php
#[Singleton]
final class RagBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [ConsoleBootloader::class];
    }

    public function defineSingletons(): array
    {
        return [
            // Registry
            RagRegistryInterface::class => RagRegistry::class,

            // Factories (auto-wired with VariableResolver)
            StoreFactory::class => StoreFactory::class,
            VectorizerFactory::class => VectorizerFactory::class,

            // Symfony AI components (lazy creation)
            StoreInterface::class => static function (
                StoreFactory $factory,
                RagRegistryInterface $registry,
            ): StoreInterface {
                return $factory->create($registry->getConfig());
            },

            VectorizerInterface::class => static function (
                VectorizerFactory $factory,
                RagRegistryInterface $registry,
            ): VectorizerInterface {
                return $factory->create($registry->getConfig());
            },

            // Services
            IndexerService::class => IndexerService::class,
            RetrieverService::class => RetrieverService::class,
        ];
    }

    public function boot(
        ConfigLoaderBootloader $configLoader,
        RagParserPlugin $ragParser,
        ConsoleBootloader $console,
    ): void {
        // Register parser plugin
        $configLoader->registerParserPlugin($ragParser);

        // Register CLI commands
        $console->addCommand(RagStatusCommand::class);
        $console->addCommand(RagIndexCommand::class);
        $console->addCommand(RagClearCommand::class);
        $console->addCommand(RagReindexCommand::class);
    }
}
```

## CLI Commands Pattern

All RAG commands follow this pattern:

1. Update `DirectoriesInterface` with config path and env file
2. Run in scope with updated directories
3. Load config via `ConfigurationProvider` to trigger `RagParserPlugin`
4. Get services from container **after** config is loaded (for variable resolution)

```php
final class RagIndexCommand extends BaseCommand
{
    #[Option(name: 'config-file', shortcut: 'c')]
    protected ?string $configPath = null;

    #[Option(name: 'env', shortcut: 'e')]
    protected ?string $envFile = null;

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
    ): int {
        // 1. Update directories with config path and env file
        $dirs = $dirs
            ->determineRootPath($this->configPath)
            ->withEnvFile($this->envFile);

        // 2. Run in scope with updated directories
        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $dirs,
                ],
            ),
            scope: function (
                ConfigurationProvider $configProvider,
                RagRegistryInterface $registry,
                FileSystemLoader $loader,
                Container $container,
            ): int {
                // 3. Load configuration to trigger RagParserPlugin
                try {
                    if ($this->configPath !== null) {
                        $configLoader = $configProvider->fromPath($this->configPath);
                    } else {
                        $configLoader = $configProvider->fromDefaultLocation();
                    }
                    $configLoader->load(); // Triggers all parser plugins
                } catch (ConfigLoaderException $e) {
                    $this->output->error($e->getMessage());
                    return Command::FAILURE;
                }

                // Check if RAG is enabled
                if (!$registry->isEnabled()) {
                    $this->output->error('RAG is not enabled');
                    return Command::FAILURE;
                }

                // 4. Get services AFTER config is loaded
                // This ensures factories have resolved ${VAR} placeholders
                $indexer = $container->get(IndexerService::class);
                
                // ... command logic using $indexer
                return Command::SUCCESS;
            },
        );
    }
}
```

## Services

### IndexerService

**Location**: `rag/Service/IndexerService.php`

Wraps Symfony AI Indexer with CTX-specific logic:

```php
final readonly class IndexerService
{
    private Indexer $indexer;
    private TextSplitTransformer $transformer;

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

        $this->indexer = new Indexer(
            vectorizer: $this->vectorizer,
            store: $this->store,
            transformer: $this->transformer,
        );
    }

    public function index(
        string $content,
        DocumentType $type,
        ?string $sourcePath = null,
    ): IndexResult;

    public function indexBatch(array $documents): IndexResult;
}
```

### RetrieverService

**Location**: `rag/Service/RetrieverService.php`

```php
final readonly class RetrieverService
{
    private Retriever $retriever;

    public function __construct(
        StoreInterface $store,
        VectorizerInterface $vectorizer,
    ) {
        $this->retriever = new Retriever($vectorizer, $store);
    }

    public function search(
        string $query,
        ?DocumentType $type = null,
        ?string $pathPrefix = null,
        int $limit = 10,
        float $minScore = 0.3,
    ): array;
}
```

## MCP Tools Pattern

### Tool Action

**Location**: `rag/MCP/Tools/RagSearch/RagSearchAction.php`

```php
#[Tool(
    name: 'rag-search',
    description: 'Search the RAG knowledge base',
)]
#[InputSchema(RagSearchRequest::class)]
final readonly class RagSearchAction
{
    public function __construct(
        private RagSearchHandler $handler,
    ) {}

    #[Post(path: '/tools/call/rag-search')]
    public function __invoke(RagSearchRequest $request): CallToolResult
    {
        return $this->handler->handle($request);
    }
}
```

### Tool Handler

**Location**: `rag/MCP/Tools/RagSearch/RagSearchHandler.php`

```php
final readonly class RagSearchHandler
{
    public function __construct(
        private RetrieverService $retriever,
        private RagRegistryInterface $registry,
    ) {}

    public function handle(RagSearchRequest $request): CallToolResult
    {
        if (!$this->registry->isEnabled()) {
            return CallToolResult::text('RAG is not enabled');
        }

        $results = $this->retriever->search(
            query: $request->query,
            type: $request->type ? DocumentType::tryFrom($request->type) : null,
            limit: $request->limit,
        );

        // Format and return results
    }
}
```

### Request DTO

**Location**: `rag/MCP/Tools/RagSearch/Dto/RagSearchRequest.php`

```php
final readonly class RagSearchRequest
{
    public function __construct(
        public string $query,
        public ?string $type = null,
        public int $limit = 10,
    ) {}
}
```

## Document Types

**Location**: `rag/Document/DocumentType.php`

```php
enum DocumentType: string
{
    case Architecture = 'architecture';
    case Api = 'api';
    case Testing = 'testing';
    case Convention = 'convention';
    case Tutorial = 'tutorial';
    case Reference = 'reference';
    case General = 'general';
}
```

## Configuration Example

```yaml
# context.yaml
rag:
  enabled: true
  store:
    driver: qdrant
    qdrant:
      endpoint_url: ${RAG_QDRANT_URL:-http://localhost:6333}
      api_key: ${RAG_QDRANT_API_KEY:-}
      collection: ${RAG_COLLECTION:-ctx_knowledge}
      embeddings_dimension: 1536
      embeddings_distance: Cosine
  vectorizer:
    platform: openai
    model: text-embedding-3-small
    api_key: ${OPENAI_API_KEY}
  transformer:
    chunk_size: 1000
    overlap: 200
```

## Variable Resolution Flow

```
1. CLI Command Starts
   - DirectoriesInterface updated with --env file path
   - Scope created with updated DirectoriesInterface

2. Config Loading (ConfigurationProvider)
   - Reads context.yaml
   - RagParserPlugin parses "rag:" section
   - RagConfig created with raw ${VAR} strings
   - RagRegistry holds the config

3. DotEnvVariableProvider Initialization
   - Reads .env file path from DirectoriesInterface
   - Loads variables into memory via Dotenv

4. Service Creation (Container::get after config load)
   - Container creates StoreFactory with VariableResolver injected
   - StoreFactory::create() called
   - VariableResolver resolves ${RAG_QDRANT_URL} → "http://localhost:6333"
   - QdrantStore created with resolved values

5. Usage
   - IndexerService/RetrieverService use the resolved store
```

**Important**: Services that depend on resolved config values (like `StoreInterface`, `IndexerService`) must be retrieved from the container **after** `$configLoader->load()` is called. This ensures the `RagRegistry` is populated and factories can resolve variables.

## Files Reference

| File | Purpose |
|------|---------|
| `rag/RagBootloader.php` | DI registration, command registration |
| `rag/RagParserPlugin.php` | Parses `rag:` section from config |
| `rag/RagRegistry.php` | Holds parsed RagConfig |
| `rag/Config/*.php` | Configuration DTOs |
| `rag/Store/StoreFactory.php` | Creates vector store with variable resolution |
| `rag/Vectorizer/VectorizerFactory.php` | Creates vectorizer with variable resolution |
| `rag/Service/IndexerService.php` | Document indexing |
| `rag/Service/RetrieverService.php` | Semantic search |
| `rag/Console/*.php` | CLI commands |
| `rag/MCP/Tools/*/` | MCP tool implementations |

## Adding New Store Driver

1. Add to `StoreConfig::fromArray()` to parse driver-specific config
2. Add case to `StoreFactory::create()`:
   ```php
   'pinecone' => new PineconeStore(
       apiKey: $this->variableResolver->resolve($config->store->apiKey),
       // ...
   ),
   ```
3. Update JSON schema in `json-schema.json`

## Adding New Vectorizer Platform

1. Add to `VectorizerConfig::fromArray()` if needed
2. Add case to `VectorizerFactory::create()`:
   ```php
   'ollama' => OllamaPlatformFactory::create(
       baseUrl: $this->variableResolver->resolve($config->vectorizer->baseUrl),
   ),
   ```
3. Update JSON schema
