<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Alias;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\Modifier\Modifier;

/**
 * Plugin for parsing the "settings.modifiers" section
 */
final readonly class ModifierAliasesParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private AliasesRegistry $aliasesRegistry,
    ) {}

    public function getConfigKey(): string
    {
        return 'settings.modifiers';
    }

    public function supports(array $config): bool
    {
        return isset($config['settings']['modifiers'])
            && \is_array($config['settings']['modifiers']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // By default, return the config unchanged
        return $config;
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        $modifiersConfig = $config['settings']['modifiers'];

        foreach ($modifiersConfig as $alias => $modifierConfig) {
            $modifier = Modifier::from($modifierConfig);
            $this->aliasesRegistry->register($alias, $modifier);
        }

        return null;
    }
}
