<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Service;

final readonly class IndexResult
{
    public function __construct(
        public int $documentsIndexed,
        public int $chunksCreated,
        public float $processingTimeMs,
    ) {}
}
