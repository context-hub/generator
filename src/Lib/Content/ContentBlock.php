<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content;

use Butschster\ContextGenerator\Lib\Content\Block\BlockInterface;
use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Container for a collection of content blocks
 */
final class ContentBlock
{
    /**
     * @var BlockInterface[] Array of content blocks
     */
    private array $blocks = [];

    /**
     * Add a block to the collection
     *
     * @param BlockInterface $block The block to add
     */
    public function addBlock(BlockInterface $block): self
    {
        $this->blocks[] = $block;
        return $this;
    }

    /**
     * Get all blocks in the collection
     *
     * @return BlockInterface[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * Render all blocks using the provided renderer
     *
     * @param RendererInterface $renderer The renderer to use
     * @return string The rendered content
     */
    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderContent($this->blocks);
    }
}
