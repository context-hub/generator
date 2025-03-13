<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content;

use Butschster\ContextGenerator\Lib\Content\Renderer\MarkdownRenderer;
use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Factory for creating ContentBuilder instances
 */
class ContentBuilderFactory
{
    /**
     * Create a new ContentBuilderFactory
     *
     * @param RendererInterface|null $defaultRenderer The default renderer to use
     */
    public function __construct(private readonly RendererInterface $defaultRenderer = new MarkdownRenderer()) {}

    /**
     * Create a new ContentBuilder instance
     *
     * @param RendererInterface|null $renderer The renderer to use (defaults to the factory's default renderer)
     * @return ContentBuilder The new ContentBuilder instance
     */
    public function create(?RendererInterface $renderer = null): ContentBuilder
    {
        return new ContentBuilder($renderer ?? $this->defaultRenderer);
    }
}
