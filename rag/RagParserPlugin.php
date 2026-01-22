<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Psr\Log\LoggerInterface;

/**
 * Parser plugin for the 'rag' section in configuration
 */
final readonly class RagParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private RagRegistryInterface $registry,
        #[LoggerPrefix(prefix: 'rag-parser')]
        private ?LoggerInterface $logger = null,
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
        if (!$this->supports($config)) {
            return null;
        }

        \assert($this->registry instanceof RegistryInterface);

        $ragConfig = RagConfig::fromArray($config['rag']);
        $this->registry->setConfig($ragConfig);

        $this->logConfiguration($ragConfig);

        return $this->registry;
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        return $config;
    }

    private function logConfiguration(RagConfig $ragConfig): void
    {
        if ($ragConfig->isLegacyFormat()) {
            $this->logger?->info('Parsed RAG configuration (legacy format)', [
                'enabled' => $ragConfig->enabled,
                'store_driver' => $ragConfig->store?->driver,
                'collection' => $ragConfig->store?->collection,
                'vectorizer_platform' => $ragConfig->vectorizer->platform,
                'vectorizer_model' => $ragConfig->vectorizer->model,
            ]);
        } else {
            $this->logger?->info('Parsed RAG configuration (new format)', [
                'enabled' => $ragConfig->enabled,
                'servers' => $ragConfig->getServerNames(),
                'collections' => $ragConfig->getCollectionNames(),
                'vectorizer_platform' => $ragConfig->vectorizer->platform,
                'vectorizer_model' => $ragConfig->vectorizer->model,
            ]);
        }
    }
}
