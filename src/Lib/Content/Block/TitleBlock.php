<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Block for title/heading content
 */
final readonly class TitleBlock extends AbstractBlock
{
    /**
     * @param string $content The title content
     * @param int $level The heading level (1-6)
     */
    public function __construct(
        string $content,
        private int $level = 1,
    ) {
        parent::__construct($content);
    }

    /**
     * Get the heading level
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderTitleBlock($this);
    }
}
