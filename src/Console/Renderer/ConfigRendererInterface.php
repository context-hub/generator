<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

/**
 * @deprecated Should be completely redesigned
 */
interface ConfigRendererInterface
{
    /**
     * Check if this renderer supports the given configuration
     */
    public function supports(array $config): bool;

    /**
     * Render the configuration to a string representation
     */
    public function render(array $config): string;
}
