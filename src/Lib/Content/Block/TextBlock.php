<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Block for plain text content
 */
final readonly class TextBlock extends AbstractBlock
{
    public function __construct(string $content, private string $tag = '')
    {
        parent::__construct($content);
    }

    /**
     * Render the block using the provided renderer
     */
    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderTextBlock($this);
    }

    public function getTag(): string
    {
        return \trim($this->tag);
    }

    public function hasTag(): bool
    {
        return $this->getTag() !== '';
    }
}
