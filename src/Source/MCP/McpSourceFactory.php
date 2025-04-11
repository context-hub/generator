<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;
use Butschster\ContextGenerator\Source\MCP\Config\ServerConfig;
use Butschster\ContextGenerator\Source\MCP\Config\ServerRegistry;
use Butschster\ContextGenerator\Source\MCP\Operations\OperationFactory;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating McpSource instances
 */
#[LoggerPrefix(prefix: 'mcp-source-factory')]
final readonly class McpSourceFactory extends AbstractSourceFactory
{
    public function __construct(
        private ServerRegistry $serverRegistry,
        private OperationFactory $operationFactory,
        DirectoriesInterface $dirs,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct(dirs: $dirs, logger: $logger);
    }

    #[\Override]
    public function getType(): string
    {
        return 'mcp';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating MCP source', [
            'config' => $config,
        ]);

        if (!isset($config['server'])) {
            throw new \RuntimeException('MCP source must have a "server" property');
        }

        if (!isset($config['operation'])) {
            throw new \RuntimeException('MCP source must have an "operation" property');
        }

        // Get server configuration
        $serverConfig = $this->resolveServerConfig($config['server']);

        // Create operation
        $operation = $this->operationFactory->create($config['operation']);

        return new McpSource(
            serverConfig: $serverConfig,
            operation: $operation,
            description: $config['description'] ?? '',
            tags: $config['tags'] ?? [],
            modifiers: $config['modifiers'] ?? [],
        );
    }

    /**
     * Resolve server configuration from config or registry
     */
    private function resolveServerConfig(string|array $serverConfig): ServerConfig
    {
        // If server is a string, look it up in the registry
        if (\is_string($serverConfig)) {
            if (!$this->serverRegistry->has($serverConfig)) {
                throw new \RuntimeException(\sprintf('MCP server "%s" not found in registry', $serverConfig));
            }

            return $this->serverRegistry->get($serverConfig);
        }

        // Otherwise, create a new server config
        return ServerConfig::fromArray($serverConfig);
    }
}
