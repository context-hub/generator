<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader\ConfigRegistry\Parser;

use Butschster\ContextGenerator\Loader\ConfigRegistry\ConfigRegistry;

/**
 * Interface for configuration parsers
 */
interface ConfigParserInterface
{
    /**
     * Parse configuration data and return a ConfigRegistry
     *
     * @param array<mixed> $config The configuration data to parse
     */
    public function parse(array $config): ConfigRegistry;
}
