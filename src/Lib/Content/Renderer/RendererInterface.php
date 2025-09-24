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
 * Interface for content renderers
 */
interface RendererInterface
{
    /**
     * Render a code block
     */
    public function renderCodeBlock(CodeBlock $block): string;

    /**
     * Render a text block
     */
    public function renderTextBlock(TextBlock $block): string;

    /**
     * Render a title block
     */
    public function renderTitleBlock(TitleBlock $block): string;

    /**
     * Render a description block
     */
    public function renderDescriptionBlock(DescriptionBlock $block): string;

    /**
     * Render a tree view block
     */
    public function renderTreeViewBlock(TreeViewBlock $block): string;

    /**
     * Render a file stats block
     */
    public function renderFileStatsBlock(FileStatsBlock $block): string;

    /**
     * Render a separator block
     */
    public function renderSeparatorBlock(SeparatorBlock $block): string;

    /**
     * Render a comment block
     */
    public function renderCommentBlock(CommentBlock $block): string;

    /**
     * Render the final content
     */
    public function renderContent(array $blocks): string;
}
