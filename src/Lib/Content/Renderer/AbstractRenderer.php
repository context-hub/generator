<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Renderer;

use Butschster\ContextGenerator\Lib\Content\Block\BlockInterface;

/**
 * Abstract base class for content renderers
 */
abstract class AbstractRenderer implements RendererInterface
{
    /**
     * Render the final content by joining all blocks
     */
    public function renderContent(array $blocks): string
    {
        $content = '';

        foreach ($blocks as $block) {
            if (!$block instanceof BlockInterface) {
                continue;
            }

            $content .= $block->render($this);
        }

        return $content;
    }
}
