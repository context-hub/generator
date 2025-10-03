<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Parser;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;

/**
 * Combines multiple parsers into one
 */
final readonly class CompositeConfigParser implements ConfigParserInterface
{
    /** @var array<ConfigParserInterface> */
    private array $parsers;

    /**
     * @param ConfigParserInterface ...$parsers The parsers to use
     */
    public function __construct(
        ConfigParserInterface ...$parsers,
    ) {
        $this->parsers = $parsers;
    }

    public function parse(array $config): ConfigRegistry
    {
        $registry = new ConfigRegistry();

        foreach ($this->parsers as $parser) {
            $parsedRegistry = $parser->parse($config);

            foreach ($parsedRegistry->all() as $type => $typeRegistry) {
                // Only register if not already registered
                if (!$registry->has(type: $type)) {
                    $registry->register(registry: $typeRegistry);
                }
            }
        }

        return $registry;
    }
}
