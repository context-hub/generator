<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Block for directory tree view content
 */
final readonly class TreeViewBlock extends AbstractBlock
{
    /**
     * Render the block using the provided renderer
     */
    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderTreeViewBlock($this);
    }
}
