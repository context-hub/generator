<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchChunk;

final readonly class ChunkParser
{
    public function parseChunk(FileApplyPatchChunk $chunk): ParsedChunk
    {
        $contextMarker = $this->cleanContextMarker($chunk->contextMarker);
        $changes = [];

        foreach ($chunk->changes as $line) {
            $changes[] = $this->parseChangeLine($line);
        }

        return new ParsedChunk(
            contextMarker: $contextMarker,
            changes: $changes,
        );
    }

    /**
     * @param FileApplyPatchChunk[] $chunks
     * @return ParsedChunk[]
     */
    public function parseChunks(array $chunks): array
    {
        return \array_map($this->parseChunk(...), $chunks);
    }

    private function parseChangeLine(string $line): ChangeOperation
    {
        if ($line === '') {
            return new ChangeOperation(
                type: ChangeType::CONTEXT,
                content: '',
            );
        }

        $firstChar = $line[0];
        $content = \substr($line, 1);

        return match ($firstChar) {
            '+' => new ChangeOperation(ChangeType::ADD, $content),
            '-' => new ChangeOperation(ChangeType::REMOVE, $content),
            ' ' => new ChangeOperation(ChangeType::CONTEXT, $content),
            default => new ChangeOperation(ChangeType::CONTEXT, $line),
        };
    }

    private function cleanContextMarker(string $contextMarker): string
    {
        return \trim(\str_replace('@@', '', $contextMarker));
    }
}
