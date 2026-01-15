# Phase 3: MCP Tools

## Objective

Implement three MCP tools: `rag-store`, `rag-search`, `rag-manage`. All tools use IndexerService and RetrieverService from Phase 2.

---

## What We're Building

1. **rag-store** - Store documentation (uses IndexerService)
2. **rag-search** - Semantic search (uses RetrieverService)
3. **rag-manage** - View stats and manage entries

---

## Files to Create

### 3.1 rag-store Tool

#### `rag/MCP/Tools/RagStore/Dto/RagStoreRequest.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto;

use Butschster\ContextGenerator\McpServer\Attribute\Field;
use Butschster\ContextGenerator\McpServer\Projects\ProjectAwareRequest;

final readonly class RagStoreRequest implements ProjectAwareRequest
{
    public function __construct(
        #[Field(description: 'Content to store in the knowledge base')]
        public string $content,
        
        #[Field(description: 'Type: architecture, api, testing, convention, tutorial, reference, general')]
        public string $type = 'general',
        
        #[Field(description: 'Source path (e.g., "src/Auth/Service.php")')]
        public ?string $sourcePath = null,
        
        #[Field(description: 'Tags (comma-separated)')]
        public ?string $tags = null,
        
        #[Field(description: 'Project identifier')]
        public ?string $project = null,
    ) {}
    
    public function getProject(): ?string
    {
        return $this->project;
    }
    
    public function getParsedTags(): ?array
    {
        return $this->tags ? \array_map('trim', \explode(',', $this->tags)) : null;
    }
}
```

#### `rag/MCP/Tools/RagStore/RagStoreHandler.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Butschster\ContextGenerator\Rag\Service\IndexerService;

final readonly class RagStoreHandler
{
    public function __construct(
        private IndexerService $indexer,
    ) {}
    
    public function handle(RagStoreRequest $request): string
    {
        if (empty(\trim($request->content))) {
            return 'Error: Content cannot be empty.';
        }
        
        $type = DocumentType::tryFrom($request->type) ?? DocumentType::General;
        
        $result = $this->indexer->index(
            content: $request->content,
            type: $type,
            sourcePath: $request->sourcePath,
            tags: $request->getParsedTags(),
        );
        
        return \sprintf(
            "Stored in knowledge base.\nChunks: %d | Time: %.2fms",
            $result->chunksCreated,
            $result->processingTimeMs,
        );
    }
}
```

#### `rag/MCP/Tools/RagStore/RagStoreAction.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagStore;

use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagStore\Dto\RagStoreRequest;
use Php\Mcp\Schema\Content\TextContent;
use Php\Mcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Spiral\Router\Annotation\Route as Post;

#[Tool(
    name: 'rag-store',
    description: 'Store documentation or knowledge in the project knowledge base.',
    title: 'RAG Store',
)]
#[InputSchema(class: RagStoreRequest::class)]
final readonly class RagStoreAction
{
    public function __construct(
        private RagStoreHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/rag-store', name: 'tools.rag-store')]
    public function __invoke(RagStoreRequest $request): CallToolResult
    {
        $this->logger->info('RAG Store', ['type' => $request->type]);
        return new CallToolResult(content: [new TextContent($this->handler->handle($request))]);
    }
}
```

### 3.2 rag-search Tool

#### `rag/MCP/Tools/RagSearch/Dto/RagSearchRequest.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto;

use Butschster\ContextGenerator\McpServer\Attribute\Field;
use Butschster\ContextGenerator\McpServer\Attribute\Range;
use Butschster\ContextGenerator\McpServer\Projects\ProjectAwareRequest;

final readonly class RagSearchRequest implements ProjectAwareRequest
{
    public function __construct(
        #[Field(description: 'Search query in natural language')]
        public string $query,
        
        #[Field(description: 'Filter by type: architecture, api, testing, convention, tutorial, reference, general')]
        public ?string $type = null,
        
        #[Field(description: 'Filter by source path prefix')]
        public ?string $pathPrefix = null,
        
        #[Field(description: 'Maximum results')]
        #[Range(min: 1, max: 50)]
        public int $limit = 10,
        
        #[Field(description: 'Minimum relevance score (0.0-1.0)')]
        public float $minScore = 0.3,
        
        #[Field(description: 'Project identifier')]
        public ?string $project = null,
    ) {}
    
    public function getProject(): ?string
    {
        return $this->project;
    }
}
```

#### `rag/MCP/Tools/RagSearch/RagSearchHandler.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use Butschster\ContextGenerator\Rag\Service\RetrieverService;

final readonly class RagSearchHandler
{
    public function __construct(
        private RetrieverService $retriever,
    ) {}
    
    public function handle(RagSearchRequest $request): string
    {
        if (empty(\trim($request->query))) {
            return 'Error: Query cannot be empty.';
        }
        
        $type = $request->type ? DocumentType::tryFrom($request->type) : null;
        
        $results = $this->retriever->search(
            query: $request->query,
            type: $type,
            pathPrefix: $request->pathPrefix,
            limit: $request->limit,
            minScore: $request->minScore,
        );
        
        if (empty($results)) {
            return \sprintf('No results found for "%s"', $request->query);
        }
        
        $output = [\sprintf('Found %d results for "%s"', \count($results), $request->query), ''];
        
        foreach ($results as $i => $item) {
            $output[] = \sprintf('[%d] %s', $i + 1, $item->format());
            $output[] = '';
        }
        
        return \implode("\n", $output);
    }
}
```

#### `rag/MCP/Tools/RagSearch/RagSearchAction.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch;

use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use Php\Mcp\Schema\Content\TextContent;
use Php\Mcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Spiral\Router\Annotation\Route as Post;

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
        return new CallToolResult(content: [new TextContent($this->handler->handle($request))]);
    }
}
```

### 3.3 rag-manage Tool

#### `rag/MCP/Tools/RagManage/Dto/RagManageRequest.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagManage\Dto;

use Butschster\ContextGenerator\McpServer\Attribute\Field;
use Butschster\ContextGenerator\McpServer\Projects\ProjectAwareRequest;

final readonly class RagManageRequest implements ProjectAwareRequest
{
    public function __construct(
        #[Field(description: 'Action: stats')]
        public string $action = 'stats',
        
        #[Field(description: 'Project identifier')]
        public ?string $project = null,
    ) {}
    
    public function getProject(): ?string
    {
        return $this->project;
    }
}
```

#### `rag/MCP/Tools/RagManage/RagManageHandler.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagManage;

use Butschster\ContextGenerator\Rag\MCP\Tools\RagManage\Dto\RagManageRequest;
use Butschster\ContextGenerator\Rag\RagConfig;

final readonly class RagManageHandler
{
    public function __construct(
        private RagConfig $config,
    ) {}
    
    public function handle(RagManageRequest $request): string
    {
        return match ($request->action) {
            'stats' => $this->stats(),
            default => \sprintf('Unknown action: %s. Available: stats', $request->action),
        };
    }
    
    private function stats(): string
    {
        return \sprintf(
            "RAG Knowledge Base\n==================\nStore: %s\nCollection: %s\nStatus: Active",
            $this->config->store->driver,
            $this->config->store->collection,
        );
    }
}
```

#### `rag/MCP/Tools/RagManage/RagManageAction.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagManage;

use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagManage\Dto\RagManageRequest;
use Php\Mcp\Schema\Content\TextContent;
use Php\Mcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Spiral\Router\Annotation\Route as Post;

#[Tool(
    name: 'rag-manage',
    description: 'Manage the project knowledge base.',
    title: 'RAG Manage',
)]
#[InputSchema(class: RagManageRequest::class)]
final readonly class RagManageAction
{
    public function __construct(
        private RagManageHandler $handler,
        private LoggerInterface $logger,
    ) {}

    #[Post(path: '/tools/call/rag-manage', name: 'tools.rag-manage')]
    public function __invoke(RagManageRequest $request): CallToolResult
    {
        $this->logger->info('RAG Manage', ['action' => $request->action]);
        return new CallToolResult(content: [new TextContent($this->handler->handle($request))]);
    }
}
```

---

## Implementation Order

1. RagStore: Request → Handler → Action
2. RagSearch: Request → Handler → Action  
3. RagManage: Request → Handler → Action

---

## Test Cases

### Integration Tests: `tests/src/McpInspector/Tools/Rag/`

```
RagStoreToolTest.php
- test_stores_content
- test_rejects_empty_content

RagSearchToolTest.php
- test_finds_content
- test_filters_by_type
- test_handles_no_results

RagManageToolTest.php
- test_shows_stats
```

---

## Definition of Done

- [ ] All three tools work via MCP protocol
- [ ] Tools handle errors gracefully
- [ ] rag-store uses IndexerService
- [ ] rag-search uses RetrieverService
- [ ] MCP Inspector tests pass

---

## Estimated Effort

| Task           | Complexity | Time   |
|----------------|------------|--------|
| rag-store      | Medium     | 2h     |
| rag-search     | Medium     | 2h     |
| rag-manage     | Low        | 1h     |
| Tests          | Medium     | 2h     |
| **Total**      |            | **~7h** |
