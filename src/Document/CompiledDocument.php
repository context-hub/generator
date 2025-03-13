<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Document;

use Butschster\ContextGenerator\Error\ErrorCollection;

final readonly class CompiledDocument
{
    public function __construct(
        public string|\Stringable $content,
        public ErrorCollection $errors,
    ) {}
}
