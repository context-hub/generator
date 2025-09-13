<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

final readonly class ChunkApplier
{
    public function applyChanges(
        array $parsedChunks,
        array $contentLines,
        ContextMatcher $contextMatcher,
        ChangeChunkConfig $config,
    ): ApplyResult {
        $modifiedLines = $contentLines;
        $appliedChanges = [];
        $errors = [];

        // Sort chunks by their target positions (descending order to avoid position shifts)
        $chunksWithPositions = $this->resolveChunkPositions($parsedChunks, $contentLines, $contextMatcher, $config);

        if (!$this->allChunksResolved($chunksWithPositions)) {
            $errors[] = 'Some context markers could not be located';
            return new ApplyResult(success: false, modifiedContent: $contentLines, errors: $errors);
        }

        // Sort by line number in descending order to apply changes from bottom to top
        \usort($chunksWithPositions, static fn($a, $b) => $b['position'] <=> $a['position']);

        foreach ($chunksWithPositions as $chunkData) {
            $chunk = $chunkData['chunk'];
            $position = $chunkData['position'];

            try {
                $result = $this->applyChunk($chunk, $modifiedLines, $position);
                $modifiedLines = $result['lines'];
                $appliedChanges[] = $result['summary'];
            } catch (\Exception $e) {
                $errors[] = \sprintf('Failed to apply chunk at position %d: %s', $position, $e->getMessage());
            }
        }

        return new ApplyResult(
            success: empty($errors),
            modifiedContent: $modifiedLines,
            appliedChanges: $appliedChanges,
            errors: $errors,
        );
    }

    private function resolveChunkPositions(
        array $parsedChunks,
        array $contentLines,
        ContextMatcher $contextMatcher,
        ChangeChunkConfig $config,
    ): array {
        $chunksWithPositions = [];

        foreach ($parsedChunks as $chunk) {
            $matchResult = $contextMatcher->findBestMatch($contentLines, $chunk->contextMarker, $config);

            $chunksWithPositions[] = [
                'chunk' => $chunk,
                'position' => $matchResult->found ? $matchResult->lineNumber : -1,
                'confidence' => $matchResult->confidence,
                'resolved' => $matchResult->found,
            ];
        }

        return $chunksWithPositions;
    }

    private function allChunksResolved(array $chunksWithPositions): bool
    {
        foreach ($chunksWithPositions as $chunkData) {
            if (!$chunkData['resolved']) {
                return false;
            }
        }

        return true;
    }

    private function applyChunk(ParsedChunk $chunk, array $lines, int $contextPosition): array
    {
        $modifiedLines = $lines;
        $addedLines = 0;
        $removedLines = 0;
        $currentPosition = $contextPosition;

        // Process changes sequentially from the context position
        foreach ($chunk->changes as $change) {
            switch ($change->type) {
                case ChangeType::CONTEXT:
                    // Verify context line matches and advance position
                    if ($currentPosition < \count($modifiedLines)) {
                        $actualLine = \trim($modifiedLines[$currentPosition]);
                        $expectedLine = \trim($change->content);

                        // Allow empty expected lines to match any content
                        if ($expectedLine !== '' && $actualLine !== $expectedLine) {
                            // Context mismatch - try to find the line nearby
                            $foundPosition = $this->findMatchingLine($change->content, $modifiedLines, $currentPosition);
                            if ($foundPosition !== -1) {
                                $currentPosition = $foundPosition;
                            }
                        }
                        $currentPosition++;
                    }
                    break;

                case ChangeType::REMOVE:
                    // Find and remove the line starting from current position
                    $lineToRemove = $this->findMatchingLineForward($change->content, $modifiedLines, $currentPosition);
                    if ($lineToRemove !== -1) {
                        \array_splice($modifiedLines, $lineToRemove, 1);
                        $removedLines++;
                        // Adjust current position if we removed a line before it
                        if ($lineToRemove < $currentPosition) {
                            $currentPosition--;
                        }
                    }
                    break;

                case ChangeType::ADD:
                    // Add line at current position
                    \array_splice($modifiedLines, $currentPosition, 0, [$change->content]);
                    $currentPosition++;
                    $addedLines++;
                    break;
            }
        }

        return [
            'lines' => $modifiedLines,
            'summary' => \sprintf(
                'Applied chunk at position %d: +%d -%d lines',
                $contextPosition,
                $addedLines,
                $removedLines,
            ),
        ];
    }

    private function findMatchingLine(string $content, array $lines, int $startPosition): int
    {
        $searchRange = 5; // Search within 5 lines of the expected position
        $start = \max(0, $startPosition - $searchRange);
        $end = \min(\count($lines), $startPosition + $searchRange);

        for ($i = $start; $i < $end; $i++) {
            if (\trim((string) $lines[$i]) === \trim($content)) {
                return $i;
            }
        }

        return -1; // Line not found
    }

    private function findMatchingLineForward(string $content, array $lines, int $startPosition): int
    {
        $searchRange = 10; // Search forward more aggressively for removals
        $end = \min(\count($lines), $startPosition + $searchRange);

        for ($i = $startPosition; $i < $end; $i++) {
            if (\trim((string) $lines[$i]) === \trim($content)) {
                return $i;
            }
        }

        // Also try searching backward a bit
        $start = \max(0, $startPosition - 2);
        for ($i = $start; $i < $startPosition; $i++) {
            if (\trim((string) $lines[$i]) === \trim($content)) {
                return $i;
            }
        }

        return -1; // Line not found
    }
}
