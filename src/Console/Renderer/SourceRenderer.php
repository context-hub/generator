<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSource;
use Butschster\ContextGenerator\Source\Github\GithubSource;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Url\UrlSource;
use Butschster\ContextGenerator\SourceInterface;

/**
 * @deprecated Should be completely redesigned
 */
final class SourceRenderer
{
    public function renderSource(SourceInterface $source): string
    {
        if ($source instanceof FileSource) {
            return $this->renderFileSource($source);
        }

        if ($source instanceof GithubSource) {
            return $this->renderGithubSource($source);
        }

        if ($source instanceof CommitDiffSource) {
            return $this->renderGitDiffSource($source);
        }

        if ($source instanceof UrlSource) {
            return $this->renderUrlSource($source);
        }

        if ($source instanceof TextSource) {
            return $this->renderTextSource($source);
        }

        // Generic fallback for unknown source types
        return Style::keyValue("Source Type", $source::class) . "\n" .
            Style::keyValue("Description", $source->getDescription()) . "\n";
    }

    private function renderFileSource(FileSource $source): string
    {
        $output = Style::title("File Source") . "\n";
        $output .= Style::separator('=', \strlen("File Source")) . "\n";

        if ($source->hasDescription()) {
            $output .= Style::keyValue("Description", $source->getDescription()) . "\n";
        }

        $output .= Style::keyValue("Source Paths", $source->sourcePaths) . "\n";
        $output .= Style::keyValue("File Pattern", $source->filePattern) . "\n";

        if (!empty($source->notPath)) {
            $output .= Style::keyValue("Exclude Patterns", $source->notPath) . "\n";
        }

        if (!empty($source->path)) {
            $output .= Style::keyValue("Include Paths", $source->path) . "\n";
        }

        if (!empty($source->contains)) {
            $output .= Style::keyValue("Content Contains", $source->contains) . "\n";
        }

        if (!empty($source->notContains)) {
            $output .= Style::keyValue("Content Not Contains", $source->notContains) . "\n";
        }

        if (!empty($source->size)) {
            $output .= Style::keyValue("Size Constraints", $source->size) . "\n";
        }

        if (!empty($source->date)) {
            $output .= Style::keyValue("Date Constraints", $source->date) . "\n";
        }

        $output .= Style::keyValue("Ignore Unreadable Dirs", $source->ignoreUnreadableDirs) . "\n";
        $output .= Style::keyValue("Show Tree View", $source->treeView->enabled) . "\n";

        if (!empty($source->modifiers)) {
            $output .= "\n" . Style::property("Modifiers") . ": " . Style::count(\count($source->modifiers)) . "\n";
            $modifierRenderer = new ModifierRenderer();

            foreach ($source->modifiers as $index => $modifier) {
                /** @psalm-suppress InvalidOperand */
                $output .= "\n" . Style::property("Modifier") . " " . Style::itemNumber($index + 1) . ":\n";
                $modOutput = $modifierRenderer->renderModifier($modifier);
                $output .= Style::indent($modOutput);
            }
        }

        return $output;
    }

    private function renderGithubSource(GithubSource $source): string
    {
        $output = Style::title("GitHub Repository Source") . "\n";
        $output .= Style::separator('=', 23) . "\n";

        if ($source->hasDescription()) {
            $output .= Style::keyValue("Description", $source->getDescription()) . "\n";
        }

        $output .= Style::keyValue("Repository", $source->repository) . "\n";
        $output .= Style::keyValue("Branch", $source->branch) . "\n";
        $output .= Style::keyValue("Source Paths", $source->sourcePaths) . "\n";
        $output .= Style::keyValue("File Pattern", $source->filePattern) . "\n";

        if (!empty($source->notPath)) {
            $output .= Style::keyValue("Exclude Patterns", $source->notPath) . "\n";
        }

        if ($source->path !== null) {
            $output .= Style::keyValue("Include Paths", $source->path) . "\n";
        }

        if ($source->contains !== null) {
            $output .= Style::keyValue("Content Contains", $source->contains) . "\n";
        }

        if ($source->notContains !== null) {
            $output .= Style::keyValue("Content Not Contains", $source->notContains) . "\n";
        }

        $output .= Style::keyValue("Show Tree View", $source->showTreeView) . "\n";

        if ($source->githubToken !== null) {
            $output .= Style::keyValue("GitHub Token", "[HIDDEN]") . "\n";
        }

        if (!empty($source->modifiers)) {
            $output .= "\n" . Style::property("Modifiers") . ": " . Style::count(\count($source->modifiers)) . "\n";
            $modifierRenderer = new ModifierRenderer();

            foreach ($source->modifiers as $index => $modifier) {
                /** @psalm-suppress InvalidOperand */
                $output .= "\n" . Style::property("Modifier") . " " . Style::itemNumber($index + 1) . ":\n";
                $modOutput = $modifierRenderer->renderModifier($modifier);
                $output .= Style::indent($modOutput);
            }
        }

        return $output;
    }

    private function renderGitDiffSource(CommitDiffSource $source): string
    {
        $output = Style::title("Git Diff Source") . "\n";
        $output .= Style::separator('=', 14) . "\n";

        if ($source->hasDescription()) {
            $output .= Style::keyValue("Description", $source->getDescription()) . "\n";
        }

        $output .= Style::keyValue("Repository", $source->repository) . "\n";
        $output .= Style::keyValue("Commit Range", $source->commit) . "\n";
        $output .= Style::keyValue("File Pattern", $source->filePattern) . "\n";

        if (!empty($source->notPath)) {
            $output .= Style::keyValue("Exclude Patterns", $source->notPath) . "\n";
        }

        if (!empty($source->path)) {
            $output .= Style::keyValue("Include Paths", $source->path) . "\n";
        }

        if (!empty($source->contains)) {
            $output .= Style::keyValue("Content Contains", $source->contains) . "\n";
        }

        if (!empty($source->notContains)) {
            $output .= Style::keyValue("Content Not Contains", $source->notContains) . "\n";
        }

        $output .= Style::keyValue("Show Stats", $source->showStats) . "\n";

        if (!empty($source->modifiers)) {
            $output .= "\n" . Style::property("Modifiers") . ": " . Style::count(\count($source->modifiers)) . "\n";
            $modifierRenderer = new ModifierRenderer();

            foreach ($source->modifiers as $index => $modifier) {
                /** @psalm-suppress InvalidOperand */
                $output .= "\n" . Style::property("Modifier") . " " . Style::itemNumber($index + 1) . ":\n";
                $modOutput = $modifierRenderer->renderModifier($modifier);
                $output .= Style::indent($modOutput);
            }
        }

        return $output;
    }

    private function renderUrlSource(UrlSource $source): string
    {
        $output = Style::title("URL Source") . "\n";
        $output .= Style::separator('=', 10) . "\n";

        if ($source->hasDescription()) {
            $output .= Style::keyValue("Description", $source->getDescription()) . "\n";
        }

        $output .= Style::keyValue("URLs", $source->urls) . "\n";

        if ($source->hasSelector()) {
            $output .= Style::keyValue("CSS Selector", $source->getSelector()) . "\n";
        }

        return $output;
    }

    private function renderTextSource(TextSource $source): string
    {
        $output = Style::title("Text Source") . "\n";
        $output .= Style::separator('=', 11) . "\n";

        if ($source->hasDescription()) {
            $output .= Style::keyValue("Description", $source->getDescription()) . "\n";
        }

        $output .= Style::keyValue("Tag", $source->tag) . "\n";
        $output .= Style::keyValue("Content Preview", Style::contentPreview($source->content)) . "\n";

        return $output;
    }
}
