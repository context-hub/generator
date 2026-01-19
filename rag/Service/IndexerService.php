<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

final readonly class IndexerService
{
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
    }

    public function index(
        string $content,
        DocumentType $type = DocumentType::General,
        ?string $sourcePath = null,
        ?array $tags = null,
        array $extra = [],
    ): IndexResult {
        $document = new TextDocument(
            id: Uuid::v7(),
            content: $content,
            metadata: $this->metadataFactory->create($type, $sourcePath, $tags, $extra),
        );

        return $this->indexBatch([$document]);
    }

    /**
     * @param TextDocument[] $documents
     */
    public function indexBatch(array $documents): IndexResult
    {
        if ($documents === []) {
            return new IndexResult(0, 0, 0.0);
        }

        $startTime = \microtime(true);

        // Transform (chunk) documents
        $chunks = \iterator_to_array($this->transformer->transform($documents));

        if ($chunks === []) {
            return new IndexResult(
                documentsIndexed: \count($documents),
                chunksCreated: 0,
                processingTimeMs: (\microtime(true) - $startTime) * 1000,
            );
        }

        // Vectorize and store
        $vectorDocuments = $this->vectorizer->vectorize($chunks);
        $this->store->add($vectorDocuments);

        return new IndexResult(
            documentsIndexed: \count($documents),
            chunksCreated: \count($chunks),
            processingTimeMs: (\microtime(true) - $startTime) * 1000,
        );
    }
}
