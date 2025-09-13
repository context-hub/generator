<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchRequest;

final readonly class ChangeChunkProcessor
{
    public function __construct(
        private ChunkParser $parser = new ChunkParser(),
        private ContextMatcher $contextMatcher = new ContextMatcher(),
        private ChangeValidator $validator = new ChangeValidator(),
        private ChunkApplier $applier = new ChunkApplier(),
    ) {}

    public function processChanges(
        FileApplyPatchRequest $request,
        ChangeChunkConfig $config,
        string $fileContent,
    ): ProcessResult {
        $contentLines = $this->splitIntoLines($fileContent);

        // Step 1: Parse all chunks
        $parsedChunks = $this->parser->parseChunks($request->chunks);

        // Step 2: Validate chunks
        $validation = $this->validator->validateChanges($parsedChunks, $contentLines);
        if (!$validation->isValid) {
            return new ProcessResult(
                success: false,
                originalContent: $fileContent,
                modifiedContent: $fileContent,
                errors: $validation->errors,
                warnings: $validation->warnings,
            );
        }

        // Step 3: Apply changes
        $applyResult = $this->applier->applyChanges($parsedChunks, $contentLines, $this->contextMatcher, $config);

        return new ProcessResult(
            success: $applyResult->success,
            originalContent: $fileContent,
            modifiedContent: $applyResult->getModifiedContentAsString(),
            appliedChanges: $applyResult->appliedChanges,
            errors: $applyResult->errors,
            warnings: $validation->warnings,
        );
    }

    private function splitIntoLines(string $content): array
    {
        // Handle different line endings and preserve empty lines
        $lines = \preg_split('/\r\n|\r|\n/', $content);

        // Remove last empty line if it exists (common with file endings)
        if (\end($lines) === '') {
            \array_pop($lines);
        }

        return $lines;
    }
}
