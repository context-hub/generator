<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Retriever;
use Symfony\AI\Store\StoreInterface;

final readonly class RetrieverService
{
    private Retriever $retriever;

    public function __construct(
        StoreInterface $store,
        VectorizerInterface $vectorizer,
    ) {
        $this->retriever = new Retriever($vectorizer, $store);
    }

    /**
     * @return SearchResultItem[]
     */
    public function search(
        string $query,
        int $limit = 10,
        ?DocumentType $type = null,
        ?string $sourcePath = null,
    ): array {
        $options = ['limit' => $limit];

        // Build Qdrant filter if needed
        $filter = $this->buildFilter($type, $sourcePath);
        if ($filter !== null) {
            $options['filter'] = $filter;
        }

        $documents = $this->retriever->retrieve($query, $options);

        return \array_map(
            SearchResultItem::fromVectorDocument(...),
            \iterator_to_array($documents),
        );
    }

    /**
     * Build Qdrant filter structure
     * @see https://qdrant.tech/documentation/concepts/filtering/
     */
    private function buildFilter(?DocumentType $type, ?string $sourcePath): ?array
    {
        $must = [];

        if ($type !== null) {
            $must[] = [
                'key' => 'type',
                'match' => ['value' => $type->value],
            ];
        }

        if ($sourcePath !== null) {
            $must[] = [
                'key' => 'source_path',
                'match' => ['text' => $sourcePath],
            ];
        }

        if ($must === []) {
            return null;
        }

        return ['must' => $must];
    }
}
