<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

final readonly class ApplyResult
{
    public function __construct(
        public bool $success,
        /**
         * @var string[]
         */
        public array $modifiedContent,
        /**
         * @var string[]
         */
        public array $appliedChanges = [],
        /**
         * @var string[]
         */
        public array $errors = [],
    ) {}

    public function getModifiedContentAsString(): string
    {
        return \implode("\n", $this->modifiedContent);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getSummary(): string
    {
        if (!$this->success) {
            return \sprintf('Failed to apply changes: %s', \implode(', ', $this->errors));
        }

        return \sprintf(
            'Successfully applied %d change chunks. %s',
            \count($this->appliedChanges),
            \implode('; ', $this->appliedChanges),
        );
    }
}
