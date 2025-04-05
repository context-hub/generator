<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Parser;

use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Lib\Variable\Provider\ConfigVariableProvider;
use Psr\Log\LoggerInterface;

/**
 * Plugin for parsing the 'variables' section in configuration files
 */
final readonly class VariablesParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ConfigVariableProvider $variableProvider,
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'variables';
    }

    public function supports(array $config): bool
    {
        return isset($config['variables']) && \is_array($config['variables']);
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        $variables = $config['variables'];

        $this->logger?->debug('Parsing variables from config', [
            'count' => \count($variables),
            'keys' => \array_keys($variables),
        ]);

        // Update the variables in the provider
        $this->variableProvider->setVariables($variables);

        return null;
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // We don't need to modify the config, just return it as is
        return $config;
    }
}
