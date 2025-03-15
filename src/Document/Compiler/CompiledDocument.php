<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document\Compiler;

use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;

final readonly class CompiledDocument
{
    public function __construct(
        public string|\Stringable $content,
        public ErrorCollection $errors,
    ) {}
}
