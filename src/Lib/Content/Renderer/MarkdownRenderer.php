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
use Butschster\ContextGenerator\Lib\Content\Block\FileStatsBlock;

/**
 * Renderer for Markdown format
 */
final class MarkdownRenderer extends AbstractRenderer
{
    public function renderCodeBlock(CodeBlock $block): string
    {
        $language = $block->getLanguage() ?: '';
        $path = $block->getFilePath() ? "Path: `{$block->getFilePath()}`\n" : '';

        return <<<CODE
            ###  $path
            ```{$language}
            $block
            ```
            \n\n
            CODE;
    }

    public function renderTextBlock(TextBlock $block): string
    {
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }

        if ($block->hasTag()) {
            $content = \sprintf("<%s>\n%s\n</%s>\n", $block->getTag(), $content, $block->getTag());
        }

        return $content . "\n\n";
    }

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

    public function renderDescriptionBlock(DescriptionBlock $block): string
    {
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }

        return "_" . $content . "_\n\n";
    }

    public function renderTreeViewBlock(TreeViewBlock $block): string
    {
        $content = (string) $block;
        if (empty($content)) {
            return '';
        }
        return \sprintf("```\n// Structure of documents\n%s\n```\n\n", $content);
    }

    public function renderFileStatsBlock(FileStatsBlock $block): string
    {
        $filePath = $block->getFilePath() ? "File: `{$block->getFilePath()}`\n" : '';
        $size = $block->formatSize($block->getFileSize());
        $lineCount = $block->getLineCount();

        return <<<STATS
        ---
        **File Statistics**
        - **Size**: {$size}
        - **Lines**: {$lineCount}
        {$filePath}
        \n\n
        STATS;
    }

    public function renderSeparatorBlock(SeparatorBlock $block): string
    {
        return \str_repeat((string) $block, $block->getLength()) . "\n\n";
    }

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
