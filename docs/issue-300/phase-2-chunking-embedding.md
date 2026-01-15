# Phase 2: Indexer & Retriever Services

## Objective

Create CTX service wrappers around Symfony AI Store's Indexer and Retriever, configure text transformation, and create a filesystem document loader.

---

## What We're Building

1. **IndexerService** - Wraps Symfony AI Indexer with CTX defaults
2. **RetrieverService** - Wraps Symfony AI Retriever with CTX filtering
3. **FileSystemLoader** - Loads documents from filesystem with glob patterns

---

## Files to Create

### 2.1 IndexerService

#### `rag/Service/IndexerService.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Butschster\ContextGenerator\Rag\RagConfig;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

final readonly class IndexerService
{
    private Indexer $indexer;
    private TextSplitTransformer $transformer;
    
    public function __construct(
        private StoreInterface $store,
        private Vectorizer $vectorizer,
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
        DocumentType $type = DocumentType::General,
        ?string $sourcePath = null,
        ?array $tags = null,
    ): IndexResult {
        $startTime = \microtime(true);
        
        $document = new TextDocument(
            id: Uuid::v7(),
            content: $content,
            metadata: $this->metadataFactory->create($type, $sourcePath, $tags),
        );
        
        $chunks = $this->transformer->transform([$document]);
        $this->indexer->index($chunks);
        
        return new IndexResult(
            documentsIndexed: 1,
            chunksCreated: \count($chunks),
            processingTimeMs: (\microtime(true) - $startTime) * 1000,
        );
    }
    
    /**
     * @param TextDocument[] $documents
     */
    public function indexBatch(array $documents): IndexResult
    {
        $startTime = \microtime(true);
        
        $allChunks = $this->transformer->transform($documents);
        $this->indexer->index($allChunks);
        
        return new IndexResult(
            documentsIndexed: \count($documents),
            chunksCreated: \count($allChunks),
            processingTimeMs: (\microtime(true) - $startTime) * 1000,
        );
    }
}
```

#### `rag/Service/IndexResult.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

final readonly class IndexResult
{
    public function __construct(
        public int $documentsIndexed,
        public int $chunksCreated,
        public float $processingTimeMs,
    ) {}
}
```

### 2.2 RetrieverService

#### `rag/Service/RetrieverService.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Retriever;
use Symfony\AI\Store\StoreInterface;

final readonly class RetrieverService
{
    private Retriever $retriever;
    
    public function __construct(
        StoreInterface $store,
        Vectorizer $vectorizer,
    ) {
        $this->retriever = new Retriever($vectorizer, $store);
    }
    
    /**
     * @return SearchResultItem[]
     */
    public function search(
        string $query,
        ?DocumentType $type = null,
        ?string $pathPrefix = null,
        int $limit = 10,
        float $minScore = 0.3,
    ): array {
        $options = ['limit' => $limit, 'min_score' => $minScore];
        
        if ($type !== null || $pathPrefix !== null) {
            $where = [];
            if ($type !== null) {
                $where['type'] = $type->value;
            }
            if ($pathPrefix !== null) {
                $where['source_path'] = ['$startswith' => $pathPrefix];
            }
            $options['where'] = $where;
        }
        
        $documents = $this->retriever->retrieve($query, $options);
        
        return \array_map(
            static fn($doc) => SearchResultItem::fromVectorDocument($doc),
            \iterator_to_array($documents),
        );
    }
}
```

#### `rag/Service/SearchResultItem.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Symfony\AI\Store\Document\VectorDocument;

final readonly class SearchResultItem
{
    public function __construct(
        public string $id,
        public string $content,
        public float $score,
        public string $type,
        public ?string $sourcePath,
        public array $tags,
        public string $indexedAt,
    ) {}
    
    public static function fromVectorDocument(VectorDocument $doc): self
    {
        return new self(
            id: (string) $doc->id,
            content: $doc->content ?? '',
            score: $doc->score ?? 0.0,
            type: $doc->metadata->get('type', 'general'),
            sourcePath: $doc->metadata->get('source_path'),
            tags: $doc->metadata->get('tags', []),
            indexedAt: $doc->metadata->get('indexed_at', ''),
        );
    }
    
    public function format(): string
    {
        $header = \sprintf('Score: %.2f | Type: %s', $this->score, $this->type);
        if ($this->sourcePath) {
            $header .= \sprintf(' | Path: %s', $this->sourcePath);
        }
        
        $content = \strlen($this->content) > 500 
            ? \substr($this->content, 0, 500) . '...' 
            : $this->content;
        
        return "{$header}\nIndexed: {$this->indexedAt}\n---\n{$content}";
    }
}
```

### 2.3 FileSystem Loader

#### `rag/Loader/FileSystemLoader.php`

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Loader;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

final readonly class FileSystemLoader
{
    public function __construct(
        private MetadataFactory $metadataFactory,
    ) {}
    
    /**
     * @return \Generator<TextDocument>
     */
    public function load(
        string $path,
        string $pattern = '*.md',
        bool $recursive = false,
        DocumentType $type = DocumentType::General,
        ?array $tags = null,
    ): \Generator {
        $finder = new Finder();
        $finder->files()->in($path)->name($pattern);
        
        if (!$recursive) {
            $finder->depth(0);
        }
        
        foreach ($finder as $file) {
            $content = $file->getContents();
            if (empty(\trim($content))) {
                continue;
            }
            
            yield new TextDocument(
                id: Uuid::v7(),
                content: $content,
                metadata: $this->metadataFactory->create(
                    type: $type,
                    sourcePath: $file->getRelativePathname(),
                    tags: $tags,
                    extra: [
                        'filename' => $file->getFilename(),
                        'size' => $file->getSize(),
                    ],
                ),
            );
        }
    }
    
    public function count(string $path, string $pattern = '*.md', bool $recursive = false): int
    {
        $finder = new Finder();
        $finder->files()->in($path)->name($pattern);
        if (!$recursive) {
            $finder->depth(0);
        }
        return $finder->count();
    }
}
```

---

## Implementation Order

1. `IndexResult` → `SearchResultItem` (value objects)
2. `IndexerService`
3. `RetrieverService`
4. `FileSystemLoader`

---

## Test Cases

### Unit Tests: `tests/src/Unit/Rag/Service/`

```
IndexerServiceTest.php
- test_indexes_content_with_metadata
- test_batch_indexing

RetrieverServiceTest.php
- test_searches_by_query
- test_filters_by_type
- test_filters_by_path_prefix

FileSystemLoaderTest.php
- test_loads_markdown_files
- test_respects_pattern
- test_recursive_loading
- test_skips_empty_files
```

---

## Definition of Done

- [ ] IndexerService wraps Symfony Indexer with CTX metadata
- [ ] RetrieverService wraps Symfony Retriever with CTX filtering
- [ ] FileSystemLoader scans directories with glob patterns
- [ ] SearchResultItem formats results for output
- [ ] All unit tests pass

---

## Estimated Effort

| Task             | Complexity | Time   |
|------------------|------------|--------|
| IndexerService   | Medium     | 2h     |
| RetrieverService | Medium     | 2h     |
| FileSystemLoader | Low        | 1.5h   |
| Value objects    | Low        | 1h     |
| Tests            | Medium     | 2h     |
| **Total**        |            | **~8h** |
