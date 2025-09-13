<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

final readonly class ValidationResult
{
    public function __construct(
        public bool $isValid,
        /**
         * @var string[]
         */
        public array $errors = [],
        /**
         * @var string[]
         */
        public array $warnings = [],
    ) {}

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    public function getAllMessages(): array
    {
        return \array_merge($this->errors, $this->warnings);
    }
}
