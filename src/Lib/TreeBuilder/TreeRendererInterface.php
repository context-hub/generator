<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TreeBuilder;

/**
 * Interface for tree structure renderers
 */
interface TreeRendererInterface
{
    /**
     * Render a tree structure in a specific format
     *
     * @param array<string, mixed> $tree The tree structure to render
     * @param array<string, mixed> $options Additional rendering options
     * @return string The rendered tree
     */
    public function render(
        array $tree,
        array $options = [],
    ): string;
}
