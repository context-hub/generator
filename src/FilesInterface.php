<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

interface FilesInterface
{
    public function ensureDirectory(string $directory): bool;

    public function write(string $filename, string $content): void;

    public function read(string $filename): string|false;
}
