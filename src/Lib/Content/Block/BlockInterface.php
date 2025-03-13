<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Interface for all content blocks
 */
interface BlockInterface extends \Stringable
{
    /**
     * Render the block using the provided renderer
     */
    public function render(RendererInterface $renderer): string;
}
