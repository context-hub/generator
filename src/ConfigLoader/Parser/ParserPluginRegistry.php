<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Parser;

use Butschster\ContextGenerator\Document\DocumentsParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\AliasesRegistry;
use Butschster\ContextGenerator\Modifier\Alias\ModifierAliasesParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;

final class ParserPluginRegistry
{
    public function __construct(
        /** @var array<ConfigParserPluginInterface> */
        private array $plugins = [],
    ) {}

    public static function createDefault(): self
    {
        $modifierResolver = new ModifierResolver(
            aliasesRegistry: $aliases = new AliasesRegistry(),
        );

        return new self([
            new ModifierAliasesParserPlugin(
                aliasesRegistry: $aliases,
            ),
            new DocumentsParserPlugin(
                modifierResolver: $modifierResolver,
            ),
        ]);
    }

    /**
     * Register a parser plugin
     */
    public function register(ConfigParserPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    /**
     * Get all registered parser plugins
     *
     * @return array<ConfigParserPluginInterface>
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }
}
