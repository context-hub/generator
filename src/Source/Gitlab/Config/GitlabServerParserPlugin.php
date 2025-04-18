<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab\Config;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Psr\Log\LoggerInterface;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;

/**
 * Plugin for parsing the "settings.gitlab.servers" section
 */
final readonly class GitlabServerParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ServerRegistry $serverRegistry,
        #[LoggerPrefix(prefix: 'gitlab-server-parser')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'settings.gitlab.servers';
    }

    public function supports(array $config): bool
    {
        return isset($config['settings']['gitlab']['servers'])
            && \is_array($config['settings']['gitlab']['servers']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // By default, return the config unchanged
        return $config;
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            $this->logger?->debug('No GitLab server settings found in configuration');
            return null;
        }

        $serversConfig = $config['settings']['gitlab']['servers'];
        $this->logger?->info('Parsing GitLab server configurations', [
            'serverCount' => \count($serversConfig),
        ]);

        foreach ($serversConfig as $name => $serverConfig) {
            try {
                $this->logger?->debug('Parsing server configuration', [
                    'name' => $name,
                ]);

                $server = ServerConfig::fromArray($serverConfig);
                $this->serverRegistry->register($name, $server);

                $this->logger?->debug('Registered GitLab server', [
                    'name' => $name,
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning("Failed to register GitLab server '{$name}'", [
                    'error' => $e->getMessage(),
                    'config' => $serverConfig,
                ]);
            }
        }

        $this->logger?->info('GitLab server configurations parsed successfully', [
            'registeredServers' => \count($this->serverRegistry->all()),
        ]);

        return null;
    }
}
