<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Parser;

use Butschster\ContextGenerator\ConfigLoader\Registry\ConfigRegistry;

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
                if (!$registry->has($type)) {
                    $registry->register($typeRegistry);
                }
            }
        }

        return $registry;
    }
}
