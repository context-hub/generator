<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Block for visual separators
 */
final readonly class SeparatorBlock extends AbstractBlock
{
    /**
     * Create a new separator block
     *
     * @param string $content The separator character(s) to use
     * @param int $length The length of the separator
     */
    public function __construct(
        string $content = '-',
        private int $length = 60,
    ) {
        parent::__construct($content);
    }

    /**
     * Get the length of the separator
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Render the block using the provided renderer
     */
    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderSeparatorBlock($this);
    }
}
