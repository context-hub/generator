<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content;

use Butschster\ContextGenerator\Lib\Content\Block\BlockInterface;
use Butschster\ContextGenerator\Lib\Content\Block\CodeBlock;
use Butschster\ContextGenerator\Lib\Content\Block\CommentBlock;
use Butschster\ContextGenerator\Lib\Content\Block\DescriptionBlock;
use Butschster\ContextGenerator\Lib\Content\Block\SeparatorBlock;
use Butschster\ContextGenerator\Lib\Content\Block\TextBlock;
use Butschster\ContextGenerator\Lib\Content\Block\TitleBlock;
use Butschster\ContextGenerator\Lib\Content\Block\TreeViewBlock;
use Butschster\ContextGenerator\Lib\Content\Block\FileStatsBlock;
use Butschster\ContextGenerator\Lib\Content\Renderer\MarkdownRenderer;
use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Builder for creating structured content with various block types
 */
final class ContentBuilder implements \Stringable
{
    private readonly ContentBlock $content;

    /**
     * Create a new ContentBuilder
     */
    public function __construct(private RendererInterface $renderer = new MarkdownRenderer())
    {
        $this->content = new ContentBlock();
    }

    /**
     * Create a new ContentBuilder instance
     *
     * @param RendererInterface|null $renderer The renderer to use
     */
    public static function create(?RendererInterface $renderer = null): self
    {
        return new self($renderer);
    }

    /**
     * Set the renderer to use
     *
     * @param RendererInterface $renderer The renderer to use
     */
    public function setRenderer(RendererInterface $renderer): self
    {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * Get the renderer being used
     *
     */
    public function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }

    /**
     * Add a custom block to the content
     *
     * @param BlockInterface $block The block to add
     */
    public function addBlock(BlockInterface $block): self
    {
        $this->content->addBlock(block: $block);
        return $this;
    }

    /**
     * Add a title block
     *
     * @param string $title The title text
     * @param int $level The heading level (1-6)
     */
    public function addTitle(string $title, int $level = 1): self
    {
        return $this->addBlock(block: new TitleBlock(content: $title, level: $level));
    }

    /**
     * Add a text block
     *
     * @param string $text The text content
     */
    public function addText(string $text): self
    {
        return $this->addBlock(block: new TextBlock(content: $text));
    }

    /**
     * Add a description block
     *
     * @param string $description The description text
     */
    public function addDescription(string $description): self
    {
        return $this->addBlock(block: new DescriptionBlock(content: $description));
    }

    /**
     * Add a code block
     *
     * @param string $code The code content
     * @param string|null $language The language for syntax highlighting
     */
    public function addCodeBlock(string $code, ?string $language = null, ?string $path = null): self
    {
        return $this->addBlock(block: new CodeBlock(content: $code, language: $language, filepath: $path));
    }

    /**
     * Add a tree view block
     *
     * @param string $treeView The tree view content
     */
    public function addTreeView(string $treeView): self
    {
        return $this->addBlock(block: new TreeViewBlock(content: $treeView));
    }

    /**
     * Add a file stats block
     *
     * @param int $fileSize The file size in bytes
     * @param int $lineCount The number of lines in the file
     * @param string|null $filePath The file path (optional)
     */
    public function addFileStats(int $fileSize, int $lineCount, ?string $filePath = null): self
    {
        return $this->addBlock(block: new FileStatsBlock(content: '', fileSize: $fileSize, lineCount: $lineCount, filePath: $filePath));
    }

    /**
     * Add a separator block
     *
     * @param string $separator The separator character(s)
     * @param int $length The length of the separator
     */
    public function addSeparator(string $separator = '-', int $length = 60): self
    {
        return $this->addBlock(block: new SeparatorBlock(content: $separator, length: $length));
    }

    /**
     * Add a comment block
     *
     * @param string $comment The comment text
     */
    public function addComment(string $comment): self
    {
        return $this->addBlock(block: new CommentBlock(content: $comment));
    }

    /**
     * Merge another ContentBuilder into this one
     */
    public function merge(self $builder): self
    {
        foreach ($builder->content->getBlocks() as $block) {
            $this->addBlock(block: $block);
        }

        return $this;
    }

    /**
     * Build and return the final content
     *
     * @return string The rendered content
     */
    public function build(): string
    {
        return $this->content->render(renderer: $this->renderer);
    }

    public function __toString(): string
    {
        return (string) \preg_replace(pattern: "/(\r\n|\n)+$/", replacement: '', subject: $this->build());
    }
}
