<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

final readonly class ChangeOperation
{
    public function __construct(
        public ChangeType $type,
        public string $content,
    ) {}

    public function isAddition(): bool
    {
        return $this->type === ChangeType::ADD;
    }

    public function isRemoval(): bool
    {
        return $this->type === ChangeType::REMOVE;
    }

    public function isContext(): bool
    {
        return $this->type === ChangeType::CONTEXT;
    }
}
