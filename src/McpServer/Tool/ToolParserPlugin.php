<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Psr\Log\LoggerInterface;

/**
 * Plugin for parsing 'tools' section in configuration files.
 */
final readonly class ToolParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ToolRegistryInterface $toolRegistry,
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'tools';
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        \assert($this->toolRegistry instanceof RegistryInterface);

        if (!$this->supports($config)) {
            return null;
        }

        $this->logger?->debug('Parsing tools configuration', [
            'count' => \count($config['tools']),
        ]);

        foreach ($config['tools'] as $index => $toolConfig) {
            try {
                $tool = ToolDefinition::fromArray($toolConfig);
                $this->toolRegistry->register($tool);

                $this->logger?->debug('Tool parsed and registered', [
                    'id' => $tool->id,
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to parse tool', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);

                throw new \InvalidArgumentException(
                    \sprintf('Failed to parse tool at index %d: %s', $index, $e->getMessage()),
                    previous: $e,
                );
            }
        }

        return $this->toolRegistry;
    }

    public function supports(array $config): bool
    {
        return isset($config['tools']) && \is_array($config['tools']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // This plugin doesn't modify the configuration
        return $config;
    }
}
