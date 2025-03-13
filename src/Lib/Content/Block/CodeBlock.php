<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Content\Block;

use Butschster\ContextGenerator\Lib\Content\Renderer\RendererInterface;

/**
 * Block for code snippets with optional syntax highlighting
 */
final readonly class CodeBlock extends AbstractBlock
{
    /**
     * @param string $content The code content
     * @param string|null $language The language for syntax highlighting (optional)
     */
    public function __construct(
        string $content,
        private ?string $language = null,
        private ?string $filepath = null,
    ) {
        parent::__construct($content);
    }

    /**
     * Get the language for syntax highlighting
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * Get the file path for the code block
     */
    public function getFilePath(): ?string
    {
        return $this->filepath;
    }

    /**
     * Render the block using the provided renderer
     */
    public function render(RendererInterface $renderer): string
    {
        return $renderer->renderCodeBlock($this);
    }
}
