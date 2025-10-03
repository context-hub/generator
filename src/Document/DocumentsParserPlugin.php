<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Source\Registry\SourceProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin for parsing the "documents" section of the configuration
 */
final readonly class DocumentsParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private SourceProviderInterface $sources,
        private ModifierResolver $modifierResolver = new ModifierResolver(),
        #[LoggerPrefix(prefix: 'documents-parser-plugin')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'documents';
    }

    public function supports(array $config): bool
    {
        return isset($config['documents']) && \is_array(value: $config['documents']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // By default, return the config unchanged
        return $config;
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports(config: $config)) {
            return null;
        }

        $registry = new DocumentRegistry();

        foreach ($config['documents'] as $index => $docData) {
            if (!isset($docData['description'], $docData['outputPath'])) {
                throw new \RuntimeException(
                    message: \sprintf('Document at index %d must have "description" and "outputPath"', $index),
                );
            }

            // Parse document modifiers if present
            $documentModifiers = [];
            if (isset($docData['modifiers']) && \is_array(value: $docData['modifiers'])) {
                $documentModifiers = $this->parseModifiers(modifiersConfig: $docData['modifiers']);
            }

            // Parse document tags if present
            $documentTags = [];
            if (isset($docData['tags']) && \is_array(value: $docData['tags'])) {
                $documentTags = \array_map(callback: \strval(...), array: $docData['tags']);
            }

            $document = Document::create(
                description: (string) $docData['description'],
                outputPath: (string) $docData['outputPath'],
                overwrite: (bool) ($docData['overwrite'] ?? true),
                modifiers: $documentModifiers,
                tags: $documentTags,
            );

            if (isset($docData['sources']) && \is_array(value: $docData['sources'])) {
                foreach ($docData['sources'] as $sourceIndex => $sourceData) {
                    try {
                        $this->logger?->debug(
                            \sprintf('Creating source at index %d', $sourceIndex),
                            [
                                'document' => $document,
                                'sourceData' => $sourceData,
                            ],
                        );

                        $type = $sourceData['type'] ?? null;

                        if (!$this->sources->has($type)) {
                            $this->logger?->warning(
                                \sprintf('Source type "%s" not registered', $type),
                                [
                                    'document' => $document,
                                    'sourceData' => $sourceData,
                                ],
                            );

                            continue;
                        }

                        $document->addSource($this->sources->create($type, $sourceData));
                    } catch (\RuntimeException $e) {
                        $document->addError(error: $e->getMessage());
                        $this->logger?->error(
                            \sprintf('Failed to create source at index %d: %s', $sourceIndex, $e->getMessage()),
                            [
                                'document' => $document,
                                'sourceData' => $sourceData,
                            ],
                        );
                    }
                }
            }

            $registry->register(document: $document);
        }

        return $registry;
    }

    /**
     * Parse modifiers configuration
     *
     * @return array<Modifier>
     */
    private function parseModifiers(array $modifiersConfig): array
    {
        return $this->modifierResolver->resolveAll(references: $modifiersConfig);
    }
}
