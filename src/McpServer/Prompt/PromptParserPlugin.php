<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\PromptParsingException;
use Psr\Log\LoggerInterface;

/**
 * Plugin for parsing 'prompts' section in configuration files.
 */
final readonly class PromptParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private PromptRegistryInterface $promptRegistry,
        private PromptConfigFactory $promptFactory = new PromptConfigFactory(),
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'prompts';
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        \assert($this->promptRegistry instanceof RegistryInterface);

        if (!$this->supports($config)) {
            return null;
        }

        $this->logger?->debug('Parsing prompts configuration', [
            'count' => \count($config['prompts']),
        ]);

        foreach ($config['prompts'] as $index => $promptConfig) {
            try {
                $prompt = $this->promptFactory->createFromConfig($promptConfig);
                $this->promptRegistry->register($prompt);

                $this->logger?->debug('Prompt parsed and registered', [
                    'id' => $prompt->id,
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to parse prompt', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);

                throw new PromptParsingException(
                    \sprintf('Failed to parse prompt at index %d: %s', $index, $e->getMessage()),
                    previous: $e,
                );
            }
        }

        return $this->promptRegistry;
    }

    public function supports(array $config): bool
    {
        return isset($config['prompts']) && \is_array($config['prompts']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // This plugin doesn't modify the configuration
        return $config;
    }
}
