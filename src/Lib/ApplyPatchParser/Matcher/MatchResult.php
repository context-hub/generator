<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher;

final readonly class MatchResult
{
    public function __construct(
        public int $lineNumber,
        public float $confidence,
        public bool $found = true,
        public string $reason = '',
    ) {}

    public static function notFound(string $reason = 'Context marker not found'): self
    {
        return new self(
            lineNumber: -1,
            confidence: 0.0,
            found: false,
            reason: $reason,
        );
    }

    public static function found(int $lineNumber, float $confidence): self
    {
        return new self(
            lineNumber: $lineNumber,
            confidence: $confidence,
            found: true,
        );
    }

    public function isAcceptable(float $minConfidence): bool
    {
        return $this->found && $this->confidence >= $minConfidence;
    }
}
