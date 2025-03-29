<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\RenderStrategy;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Config\RenderConfig;

/**
 * Interface for git diff rendering strategies
 */
interface RenderStrategyInterface
{
    /**
     * Render git diffs in a specific format
     *
     * @param array<string, array{file: string, diff: string, stats: string}> $diffs Array of diffs with metadata
     */
    public function render(array $diffs, RenderConfig $config): ContentBuilder;
}
