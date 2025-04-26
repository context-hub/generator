<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document\Compiler;

use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;

final readonly class CompiledDocument
{
    public function __construct(
        public string|\Stringable $content,
        public ErrorCollection $errors,
        public ?string $outputPath = null,
        public ?string $contextPath = null,
    ) {}

    public function withOutputPath(string $outputPath, string $contextPath): self
    {
        return new self(
            content: $this->content,
            errors: $this->errors,
            outputPath: $outputPath,
            contextPath: $contextPath,
        );
    }
}
