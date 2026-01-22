<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\MCP\Tools\RagSearch\Dto\RagSearchRequest;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;

final readonly class RagSearchHandler
{
    public function __construct(
        private ServiceFactory $serviceFactory,
        private string $collectionName = 'default',
    ) {}

    public function handle(RagSearchRequest $request): string
    {
        if (\trim($request->query) === '') {
            throw new \InvalidArgumentException('Query cannot be empty');
        }

        $type = $request->type !== null ? DocumentType::tryFrom($request->type) : null;
        $retriever = $this->serviceFactory->getRetriever($this->collectionName);

        $results = $retriever->search(
            query: $request->query,
            limit: $request->limit,
            type: $type,
            sourcePath: $request->sourcePath,
        );

        if ($results === []) {
            return \sprintf('No results found for "%s" in [%s]', $request->query, $this->collectionName);
        }

        $output = [\sprintf('Found %d results for "%s" in [%s]', \count($results), $request->query, $this->collectionName), ''];

        foreach ($results as $i => $item) {
            $output[] = \sprintf('--- Result %d ---', $i + 1);
            $output[] = $item->format();
            $output[] = '';
        }

        return \implode("\n", $output);
    }

    /**
     * Create handler for specific collection.
     */
    public function withCollection(string $collectionName): self
    {
        return new self($this->serviceFactory, $collectionName);
    }
}
