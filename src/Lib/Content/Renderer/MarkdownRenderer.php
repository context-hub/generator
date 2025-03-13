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
 * Renderer for Markdown format
 */
final class MarkdownRenderer extends AbstractRenderer
{
    /**
     * Render a code block
     */
    public function renderCodeBlock(CodeBlock $block): string
    {
        $language = $block->getLanguage() ?: '';
        $path = $block->getFilePath() ? "// Path: {$block->getFilePath()}\n" : '';

        return <<<CODE
            ```{$language}
            $path
            $block
            ```
            \n\n
            CODE;
    }

    /**
     * Render a text block
     */
    public function renderTextBlock(TextBlock $block): string
    {
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }
        return $content . "\n\n";
    }

    /**
     * Render a title block
     */
    public function renderTitleBlock(TitleBlock $block): string
    {
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }

        $level = $block->getLevel();
        $prefix = \str_repeat('#', \min(6, \max(1, $level)));

        return "{$prefix} " . $content . "\n\n";
    }

    /**
     * Render a description block
     */
    public function renderDescriptionBlock(DescriptionBlock $block): string
    {
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }

        return "_" . $content . "_\n\n";
    }

    /**
     * Render a tree view block
     */
    public function renderTreeViewBlock(TreeViewBlock $block): string
    {
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }
        return "```\n" . $content . "\n```\n\n";
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
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }

        $lines = \explode("\n", $content);
        $result = '';

        foreach ($lines as $line) {
            $result .= "// {$line}\n";
        }

        return $result . "\n\n";
    }
}
