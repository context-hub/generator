<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content;

use Butschster\ContextGenerator\Lib\Content\Renderer\MarkdownRenderer;
use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

final readonly class ContentBuilderFactory
{
    public function __construct(private RendererInterface $defaultRenderer = new MarkdownRenderer()) {}

    public function create(?RendererInterface $renderer = null): ContentBuilder
    {
        return new ContentBuilder($renderer ?? $this->defaultRenderer);
    }
}
