<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Renderer;

use Butschster\ContextGenerator\Lib\Content\Block\BlockInterface;

/**
 * Abstract base class for content renderers
 */
abstract class AbstractRenderer implements RendererInterface
{
    public function renderContent(array $blocks): string
    {
        $content = '';

        foreach ($blocks as $block) {
            if (!$block instanceof BlockInterface) {
                continue;
            }

            $content .= \preg_replace(pattern: "/(\r\n|\n)+$/", replacement: "\n", subject: $block->render($this));
        }

        return $content;
    }
}
