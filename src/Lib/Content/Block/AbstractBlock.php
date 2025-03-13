<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Abstract base class for content blocks
 */
abstract readonly class AbstractBlock implements BlockInterface
{
    /**
     * @param string $content The content of the block
     */
    public function __construct(
        protected string $content,
    ) {}

    /**
     * Render the block using the provided renderer
     */
    abstract public function render(RendererInterface $renderer): string;

    /**
     * Get the content of the block
     */
    public function __toString(): string
    {
        return $this->content;
    }
}
