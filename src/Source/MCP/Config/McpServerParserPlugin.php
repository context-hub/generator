<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Config;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Psr\Log\LoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Plugin for parsing the "settings.mcp.servers" section
 */
final readonly class McpServerParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ServerRegistry $serverRegistry,
        #[LoggerPrefix(prefix: 'mcp-server-parser')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'settings.mcp.servers';
    }

    public function supports(array $config): bool
    {
        return isset($config['settings']['mcp']['servers'])
            && \is_array($config['settings']['mcp']['servers']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // By default, return the config unchanged
        return $config;
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            $this->logger?->debug('No MCP server settings found in configuration');
            return null;
        }

        $serversConfig = $config['settings']['mcp']['servers'];
        $this->logger?->info('Parsing MCP server configurations', [
            'serverCount' => \count($serversConfig),
        ]);

        foreach ($serversConfig as $name => $serverConfig) {
            try {
                $this->logger?->debug('Parsing server configuration', [
                    'name' => $name,
                ]);

                $server = ServerConfig::fromArray($serverConfig);
                $this->serverRegistry->register($name, $server);

                $this->logger?->debug('Registered MCP server', [
                    'name' => $name,
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning("Failed to register MCP server '{$name}'", [
                    'error' => $e->getMessage(),
                    'config' => $serverConfig,
                ]);
            }
        }

        $this->logger?->info('MCP server configurations parsed successfully', [
            'registeredServers' => \count($this->serverRegistry->all()),
        ]);

        return null;
    }
}
