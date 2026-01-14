<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php\Result;

/**
 * Represents a reference to another PHP file/class.
 */
final readonly class FileReference
{
    public function __construct(
        public string $fqcn,
        public ?string $resolvedPath,
        public bool $isVendor,
        public string $type,
    ) {}

    public static function local(string $fqcn, ?string $path, string $type = 'use'): self
    {
        return new self(
            fqcn: $fqcn,
            resolvedPath: $path,
            isVendor: false,
            type: $type,
        );
    }

    public function formatComment(): string
    {
        return $this->resolvedPath ?? '(unresolved)';
    }
}
