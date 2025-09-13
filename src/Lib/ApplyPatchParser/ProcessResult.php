<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

final readonly class ProcessResult
{
    public function __construct(
        public bool $success,
        public string $originalContent,
        public string $modifiedContent,
        /**
         * @var string[]
         */
        public array $appliedChanges = [],
        /**
         * @var string[]
         */
        public array $errors = [],
        /**
         * @var string[]
         */
        public array $warnings = [],
    ) {}

    public function hasChanges(): bool
    {
        return $this->originalContent !== $this->modifiedContent;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getSummary(): string
    {
        if (!$this->success) {
            return \sprintf('Failed to process changes: %s', \implode(', ', $this->errors));
        }

        $summary = \sprintf('Successfully processed %d changes', \count($this->appliedChanges));

        if (!empty($this->warnings)) {
            $summary .= \sprintf(' (with %d warnings)', \count($this->warnings));
        }

        return $summary;
    }

    public function getDetailedReport(): array
    {
        return [
            'success' => $this->success,
            'hasChanges' => $this->hasChanges(),
            'appliedChanges' => $this->appliedChanges,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => $this->getSummary(),
        ];
    }
}
