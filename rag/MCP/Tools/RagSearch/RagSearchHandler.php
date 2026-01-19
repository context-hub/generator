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
        if (\trim($request->query) === '') {
            throw new \InvalidArgumentException('Query cannot be empty');
        }

        $type = $request->type !== null ? DocumentType::tryFrom($request->type) : null;

        $results = $this->retriever->search(
            query: $request->query,
            limit: $request->limit,
            type: $type,
            sourcePath: $request->sourcePath,
        );

        if ($results === []) {
            return \sprintf('No results found for "%s"', $request->query);
        }

        $output = [\sprintf('Found %d results for "%s"', \count($results), $request->query), ''];

        foreach ($results as $i => $item) {
            $output[] = \sprintf('--- Result %d ---', $i + 1);
            $output[] = $item->format();
            $output[] = '';
        }

        return \implode("\n", $output);
    }
}
