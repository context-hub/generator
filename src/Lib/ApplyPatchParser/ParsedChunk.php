<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

final readonly class ParsedChunk
{
    public function __construct(
        public string $contextMarker,
        /**
         * @var ChangeOperation[]
         */
        public array $changes,
    ) {}

    public function hasAdditions(): bool
    {
        return \array_reduce(
            $this->changes,
            static fn(bool $carry, ChangeOperation $change) => $carry || $change->isAddition(),
            false,
        );
    }

    public function hasRemovals(): bool
    {
        return \array_reduce(
            $this->changes,
            static fn(bool $carry, ChangeOperation $change) => $carry || $change->isRemoval(),
            false,
        );
    }

    public function getAdditions(): array
    {
        return \array_filter(
            $this->changes,
            static fn(ChangeOperation $change) => $change->isAddition(),
        );
    }

    public function getRemovals(): array
    {
        return \array_filter(
            $this->changes,
            static fn(ChangeOperation $change) => $change->isRemoval(),
        );
    }

    public function getContextLines(): array
    {
        return \array_filter(
            $this->changes,
            static fn(ChangeOperation $change) => $change->isContext(),
        );
    }
}
