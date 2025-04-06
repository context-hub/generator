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
        $this->content->addBlock($block);
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
        return $this->addBlock(new TitleBlock($title, $level));
    }

    /**
     * Add a text block
     *
     * @param string $text The text content
     */
    public function addText(string $text): self
    {
        return $this->addBlock(new TextBlock($text));
    }

    /**
     * Add a description block
     *
     * @param string $description The description text
     */
    public function addDescription(string $description): self
    {
        return $this->addBlock(new DescriptionBlock($description));
    }

    /**
     * Add a code block
     *
     * @param string $code The code content
     * @param string|null $language The language for syntax highlighting
     */
    public function addCodeBlock(string $code, ?string $language = null, ?string $path = null): self
    {
        return $this->addBlock(new CodeBlock($code, $language, $path));
    }

    /**
     * Add a tree view block
     *
     * @param string $treeView The tree view content
     */
    public function addTreeView(string $treeView): self
    {
        return $this->addBlock(new TreeViewBlock($treeView));
    }

    /**
     * Add a separator block
     *
     * @param string $separator The separator character(s)
     * @param int $length The length of the separator
     */
    public function addSeparator(string $separator = '-', int $length = 60): self
    {
        return $this->addBlock(new SeparatorBlock($separator, $length));
    }

    /**
     * Add a comment block
     *
     * @param string $comment The comment text
     */
    public function addComment(string $comment): self
    {
        return $this->addBlock(new CommentBlock($comment));
    }

    /**
     * Merge another ContentBuilder into this one
     */
    public function merge(self $builder): self
    {
        foreach ($builder->content->getBlocks() as $block) {
            $this->addBlock($block);
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
        return $this->content->render($this->renderer);
    }

    public function __toString(): string
    {
        return \preg_replace("/(\r\n|\n)+$/", '', $this->build());
    }
}
