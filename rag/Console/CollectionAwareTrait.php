<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Console;

use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Trait providing collection-aware functionality for RAG commands.
 */
trait CollectionAwareTrait
{
    #[Option(
        name: 'collection',
        description: 'Collection name to operate on (operates on all if not specified)',
    )]
    protected ?string $collection = null;

    /**
     * Get collection names to operate on.
     *
     * @return string[]
     * @throws \InvalidArgumentException If specified collection doesn't exist
     */
    protected function getTargetCollections(RagRegistryInterface $registry): array
    {
        $config = $registry->getConfig();
        $allCollections = $config->getCollectionNames();

        if ($this->collection !== null) {
            if (!$config->hasCollection($this->collection)) {
                throw new \InvalidArgumentException(
                    \sprintf(
                        'Collection "%s" not found. Available: %s',
                        $this->collection,
                        \implode(', ', $allCollections),
                    ),
                );
            }

            return [$this->collection];
        }

        return $allCollections;
    }

    /**
     * Output section header for collection operations.
     */
    protected function outputCollectionHeader(OutputInterface $output, string $collectionName, string $operation): void
    {
        $output->writeln('');
        $output->writeln(\sprintf('<comment>━━━ %s: %s ━━━</comment>', $operation, $collectionName));
    }
}
