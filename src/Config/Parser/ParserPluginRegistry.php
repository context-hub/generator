<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Parser;

use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class ParserPluginRegistry
{
    public function __construct(
        /** @var array<ConfigParserPluginInterface> */
        private array $plugins = [],
    ) {}

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
