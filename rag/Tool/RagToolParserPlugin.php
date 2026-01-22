<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Tool;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Rag\RagRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Parser plugin for extracting RAG-type tools from the 'tools' section.
 *
 * This plugin runs alongside the regular ToolParserPlugin but specifically
 * handles tools with `type: rag`, validating them against the RAG configuration
 * and registering them in the RagToolRegistry.
 */
final readonly class RagToolParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private RagToolRegistryInterface $toolRegistry,
        private RagRegistryInterface $ragRegistry,
        #[LoggerPrefix(prefix: 'rag-tool-parser')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        // We also process the 'tools' section, but only for RAG tools
        return 'tools';
    }

    public function supports(array $config): bool
    {
        // Only process if there are tools AND RAG is configured
        return isset($config['tools'])
            && \is_array($config['tools'])
            && $this->ragRegistry->isEnabled();
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        $ragConfig = $this->ragRegistry->getConfig();

        foreach ($config['tools'] as $index => $toolConfig) {
            // Only process RAG-type tools
            if (($toolConfig['type'] ?? '') !== 'rag') {
                continue;
            }

            try {
                $ragTool = RagToolConfig::fromArray($toolConfig);

                // Validate collection exists
                if (!$ragConfig->hasCollection($ragTool->collection)) {
                    throw new \InvalidArgumentException(
                        \sprintf(
                            'RAG tool "%s" references non-existent collection "%s". Available: %s',
                            $ragTool->id,
                            $ragTool->collection,
                            \implode(', ', $ragConfig->getCollectionNames()),
                        ),
                    );
                }

                $this->toolRegistry->register($ragTool);

                $this->logger?->debug('RAG tool registered', [
                    'id' => $ragTool->id,
                    'collection' => $ragTool->collection,
                    'operations' => $ragTool->operations,
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to parse RAG tool', [
                    'index' => $index,
                    'id' => $toolConfig['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                throw new \InvalidArgumentException(
                    \sprintf('Failed to parse RAG tool at index %d: %s', $index, $e->getMessage()),
                    previous: $e,
                );
            }
        }

        $this->logger?->info('Parsed RAG tools', [
            'count' => \count($this->toolRegistry->all()),
        ]);

        // Return null as we don't own a registry - RagToolRegistry is managed separately
        return null;
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        return $config;
    }
}
