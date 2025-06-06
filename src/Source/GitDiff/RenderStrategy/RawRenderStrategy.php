<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\RenderStrategy;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Config\RenderConfig;

/**
 * Renders git diffs in raw format (default)
 *
 * This strategy renders the diffs exactly as they come from git
 * with standard +/- notation for added/removed lines.
 */
final readonly class RawRenderStrategy implements RenderStrategyInterface
{
    public function render(array $diffs, RenderConfig $config): ContentBuilder
    {
        $builder = new ContentBuilder();

        // Add each diff
        foreach ($diffs as $file => $diffData) {
            // Only show stats if configured to do so
            if ($config->showStats && !empty($diffData['stats'])) {
                $builder
                    ->addTitle("Stats for {$file}", 2)
                    ->addCodeBlock($diffData['stats']);
            }

            $builder
                ->addTitle("Diff for {$file}", 2)
                ->addCodeBlock($diffData['diff'], 'diff');
        }

        return $builder;
    }
}
