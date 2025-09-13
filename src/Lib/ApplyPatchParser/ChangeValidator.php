<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

final readonly class ChangeValidator
{
    public function validateChanges(array $parsedChunks, array $contentLines): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $positions = [];

        foreach ($parsedChunks as $index => $chunk) {
            $chunkErrors = $this->validateChunk($chunk, $contentLines, $index);
            $errors = \array_merge($errors, $chunkErrors);
        }

        // Check for overlapping changes
        $overlaps = $this->detectOverlaps($positions);
        if (!empty($overlaps)) {
            $errors[] = 'Detected overlapping changes that would conflict';
        }

        return new ValidationResult(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings,
        );
    }

    private function validateChunk(ParsedChunk $chunk, array $contentLines, int $chunkIndex): array
    {
        $errors = [];

        if (empty($chunk->contextMarker)) {
            $errors[] = \sprintf('Chunk %d has empty context marker', $chunkIndex);
        }

        if (empty($chunk->changes)) {
            $errors[] = \sprintf('Chunk %d has no changes', $chunkIndex);
        }

        // Validate that removal operations match existing content
        foreach ($chunk->getRemovals() as $removal) {
            if (!$this->lineExistsInContent($removal->content, $contentLines)) {
                $errors[] = \sprintf('Chunk %d tries to remove non-existent line: "%s"', $chunkIndex, $removal->content);
            }
        }

        return $errors;
    }

    private function lineExistsInContent(string $line, array $contentLines): bool
    {
        foreach ($contentLines as $contentLine) {
            if (\trim((string) $contentLine) === \trim($line)) {
                return true;
            }
        }

        return false;
    }

    private function detectOverlaps(array $positions): array
    {
        // Implementation for detecting overlapping line ranges
        // For now, return empty array - this would need more sophisticated logic
        return [];
    }
}
