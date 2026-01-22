<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Butschster\ContextGenerator\Rag\Service\ServiceFactory;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating dynamic RAG tool actions from configuration.
 */
final readonly class RagToolFactory
{
    public function __construct(
        private RagRegistryInterface $ragRegistry,
        private RagToolRegistryInterface $toolRegistry,
        private ServiceFactory $serviceFactory,
        #[LoggerPrefix(prefix: 'rag-tool-factory')]
        private LoggerInterface $logger,
    ) {}

    /**
     * Create all dynamic tool actions from registered RAG tools.
     *
     * @return array{search: DynamicRagSearchAction[], store: DynamicRagStoreAction[]}
     */
    public function createAll(): array
    {
        $searchActions = [];
        $storeActions = [];

        foreach ($this->toolRegistry->all() as $toolConfig) {
            // Validate collection exists
            if (!$this->ragRegistry->getConfig()->hasCollection($toolConfig->collection)) {
                $this->logger->warning('RAG tool references non-existent collection', [
                    'tool' => $toolConfig->id,
                    'collection' => $toolConfig->collection,
                ]);
                continue;
            }

            if ($toolConfig->hasSearch()) {
                $searchActions[] = $this->createSearchAction($toolConfig);
                $this->logger->debug('Created search action', ['tool' => $toolConfig->id]);
            }

            if ($toolConfig->hasStore()) {
                $storeActions[] = $this->createStoreAction($toolConfig);
                $this->logger->debug('Created store action', ['tool' => $toolConfig->id]);
            }
        }

        return [
            'search' => $searchActions,
            'store' => $storeActions,
        ];
    }

    /**
     * Check if there are any dynamic RAG tools to register.
     */
    public function hasTools(): bool
    {
        return $this->toolRegistry->hasTools();
    }

    public function createSearchAction(RagToolConfig $config): DynamicRagSearchAction
    {
        return new DynamicRagSearchAction(
            config: $config,
            serviceFactory: $this->serviceFactory,
            logger: $this->logger,
        );
    }

    public function createStoreAction(RagToolConfig $config): DynamicRagStoreAction
    {
        return new DynamicRagStoreAction(
            config: $config,
            serviceFactory: $this->serviceFactory,
            logger: $this->logger,
        );
    }
}
