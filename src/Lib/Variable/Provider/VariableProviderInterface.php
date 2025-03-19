<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable\Provider;

/**
 * Interface for all variable providers
 */
interface VariableProviderInterface
{
    /**
     * Check if this provider has a variable with the given name
     */
    public function has(string $name): bool;

    /**
     * Get the value of a variable
     *
     * @param string $name Variable name
     * @return string|null Variable value or null if not found
     */
    public function get(string $name): ?string;
}
