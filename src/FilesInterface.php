<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

interface FilesInterface
{
    public function ensureDirectory(string $directory): bool;

    public function write(string $filename, string $content, bool $lock = true): bool;

    public function read(string $filename): string|false;

    public function exists(string $filename): bool;

    public function delete(string $filename): bool;
}
