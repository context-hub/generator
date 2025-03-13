<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Renderer;

use Butschster\ContextGenerator\Lib\Content\Block\CodeBlock;
use Butschster\ContextGenerator\Lib\Content\Block\CommentBlock;
use Butschster\ContextGenerator\Lib\Content\Block\DescriptionBlock;
use Butschster\ContextGenerator\Lib\Content\Block\SeparatorBlock;
use Butschster\ContextGenerator\Lib\Content\Block\TextBlock;
use Butschster\ContextGenerator\Lib\Content\Block\TitleBlock;
use Butschster\ContextGenerator\Lib\Content\Block\TreeViewBlock;

/**
 * Renderer for plain text format
 */
final class PlainTextRenderer extends AbstractRenderer
{
    /**
     * Render a code block
     */
    public function renderCodeBlock(CodeBlock $block): string
    {
        return $block . "\n\n";
    }

    /**
     * Render a text block
     */
    public function renderTextBlock(TextBlock $block): string
    {
        return $block . "\n\n";
    }

    /**
     * Render a title block
     */
    public function renderTitleBlock(TitleBlock $block): string
    {
        $content = (string) $block;
        $level = $block->getLevel();

        // For plain text, we'll use different underline characters based on level
        $underlineChar = match ($level) {
            1 => '=',
            2 => '-',
            default => '~',
        };

        $underline = \str_repeat($underlineChar, \mb_strlen($content));

        return $content . "\n" . $underline . "\n\n";
    }

    /**
     * Render a description block
     */
    public function renderDescriptionBlock(DescriptionBlock $block): string
    {
        return $block . "\n\n";
    }

    /**
     * Render a tree view block
     */
    public function renderTreeViewBlock(TreeViewBlock $block): string
    {
        return $block . "\n\n";
    }

    /**
     * Render a separator block
     */
    public function renderSeparatorBlock(SeparatorBlock $block): string
    {
        return \str_repeat((string) $block, $block->getLength()) . "\n\n";
    }

    /**
     * Render a comment block
     */
    public function renderCommentBlock(CommentBlock $block): string
    {
        $lines = \explode("\n", (string) $block);
        $result = '';

        foreach ($lines as $line) {
            $result .= "// {$line}\n";
        }

        return $result . "\n";
    }
}
