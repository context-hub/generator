<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Block for descriptive text content
 */
final readonly class DescriptionBlock extends AbstractBlock
{
    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderDescriptionBlock($this);
    }
}
