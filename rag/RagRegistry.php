<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * Registry for RAG configuration
 *
 * @implements RegistryInterface<RagConfig>
 */
#[Singleton]
final class RagRegistry implements RagRegistryInterface, RegistryInterface
{
    private RagConfig $config;

    public function __construct(
        #[LoggerPrefix(prefix: 'rag-registry')]
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->config = new RagConfig();
    }

    public function setConfig(RagConfig $config): void
    {
        $this->config = $config;
        $this->logger?->debug('RAG config set', ['enabled' => $config->enabled]);
    }

    public function getConfig(): RagConfig
    {
        return $this->config;
    }

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }

    public function getType(): string
    {
        return 'rag';
    }

    public function getItems(): array
    {
        return [$this->config];
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([$this->config]);
    }

    public function jsonSerialize(): array
    {
        $result = [
            'enabled' => $this->config->enabled,
            'vectorizer' => [
                'platform' => $this->config->vectorizer->platform,
                'model' => $this->config->vectorizer->model,
            ],
            'transformer' => [
                'chunk_size' => $this->config->transformer->chunkSize,
                'overlap' => $this->config->transformer->overlap,
            ],
        ];

        // Include legacy store info if present
        if ($this->config->store !== null) {
            $result['store'] = [
                'driver' => $this->config->store->driver,
                'endpoint_url' => $this->config->store->endpointUrl,
                'collection' => $this->config->store->collection,
                'embeddings_dimension' => $this->config->store->embeddingsDimension,
            ];
        }

        // Include new format info
        if (!empty($this->config->servers)) {
            $result['servers'] = \array_map(
                static fn($server) => [
                    'driver' => $server->driver,
                    'endpoint_url' => $server->endpointUrl,
                    'embeddings_dimension' => $server->embeddingsDimension,
                ],
                $this->config->servers,
            );
        }

        if (!empty($this->config->collections)) {
            $result['collections'] = \array_map(
                static fn($collection) => [
                    'server' => $collection->server,
                    'collection' => $collection->collection,
                    'description' => $collection->description,
                ],
                $this->config->collections,
            );
        }

        return $result;
    }
}
