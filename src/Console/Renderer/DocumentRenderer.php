<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

use Butschster\ContextGenerator\Document\Document;

/**
 * @deprecated Should be completely redesigned
 */
final class DocumentRenderer
{
    public function renderDocument(Document $document): string
    {
        $output = Style::keyValue("Description", $document->description) . "\n";
        $output .= Style::keyValue("Output Path", $document->outputPath) . "\n";
        $output .= Style::keyValue("Overwrite", $document->overwrite) . "\n\n";

        $sources = $document->getSources();

        $sourceRenderer = new SourceRenderer();

        foreach ($sources as $index => $source) {
            $sourceOutput = $sourceRenderer->renderSource($source);
            $output .= Style::indent($sourceOutput) . "\n";
        }

        return $output;
    }
}
