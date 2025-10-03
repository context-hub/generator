<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\RenderStrategy;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Config\RenderConfig;

/**
 * Renders git diffs in an enhanced markdown format
 *
 * This strategy improves readability by:
 * - Converting standard git diff syntax to markdown-friendly formatting
 * - Highlighting added/removed/changed sections more clearly
 * - Structuring the output to be more readable for both humans and LLMs
 */
final readonly class LLMFriendlyRenderStrategy implements RenderStrategyInterface
{
    public function render(array $diffs, RenderConfig $config): ContentBuilder
    {
        $builder = new ContentBuilder();

        // Handle empty diffs case
        if (empty($diffs)) {
            $builder->addText(text: "No changes found in this commit range.");
            return $builder;
        }

        // Add each diff with enhanced formatting
        foreach ($diffs as $file => $diffData) {
            // Only show stats if configured to do so
            if ($config->showStats && !empty($diffData['stats'])) {
                $builder
                    ->addTitle(title: "Stats for {$file}", level: 2)
                    ->addCodeBlock(code: $diffData['stats']);
            }

            $diff = $this->convert(diff: $diffData['diff']);

            // Add the enhanced diff
            $builder
                ->addTitle(title: "Diff for {$file}", level: 2)
                ->addCodeBlock(code: $diff, language: 'diff');
        }

        return $builder;
    }

    /**
     * Convert standard git diff output to an LLM-friendly format
     */
    private function convert(string $diff): string
    {
        if (empty($diff)) {
            return $diff;
        }

        $lines = \explode(separator: "\n", string: $diff);
        $enhancedLines = [];
        $additionBuffer = [];
        $removalBuffer = [];

        foreach ($lines as $line) {
            // Skip diff headers/metadata that start with 'diff', 'index', '---', '+++', or '@@'
            if (\str_starts_with(haystack: $line, needle: 'diff') ||
                \str_starts_with(haystack: $line, needle: 'index') ||
                \str_starts_with(haystack: $line, needle: '---') ||
                \str_starts_with(haystack: $line, needle: '+++') ||
                \preg_match(pattern: '/^@@\s+\-\d+,\d+\s+\+\d+,\d+\s+@@/', subject: $line)) {
                // Output any pending buffers before handling metadata
                $this->flushBuffers(output: $enhancedLines, additionBuffer: $additionBuffer, removalBuffer: $removalBuffer);
                continue;
            }

            // Check for added lines
            if (\str_starts_with(haystack: $line, needle: '+')) {
                $additionBuffer[] = \substr(string: $line, offset: 1); // Remove the '+' sign
                continue;
            }

            // Check for removed lines
            if (\str_starts_with(haystack: $line, needle: '-')) {
                $removalBuffer[] = \substr(string: $line, offset: 1); // Remove the '-' sign
                continue;
            }

            // For context lines, flush any pending buffers first
            $this->flushBuffers(output: $enhancedLines, additionBuffer: $additionBuffer, removalBuffer: $removalBuffer);
            $enhancedLines[] = $line;
        }

        // Ensure any remaining buffers are flushed at the end
        $this->flushBuffers(output: $enhancedLines, additionBuffer: $additionBuffer, removalBuffer: $removalBuffer);

        return \implode(separator: "\n", array: $enhancedLines);
    }

    /**
     * Flush addition and removal buffers to the output with appropriate tags
     */
    private function flushBuffers(array &$output, array &$additionBuffer, array &$removalBuffer): void
    {
        // Process removals first (to match git diff's natural presentation order)
        if (!empty($removalBuffer)) {
            $output[] = "<removed>";
            foreach ($removalBuffer as $line) {
                $output[] = $line;
            }
            $output[] = "</removed>";
            $removalBuffer = [];
        }

        // Then process additions
        if (!empty($additionBuffer)) {
            $output[] = "<added>";
            foreach ($additionBuffer as $line) {
                $output[] = $line;
            }
            $output[] = "</added>";
            $additionBuffer = [];
        }
    }
}
