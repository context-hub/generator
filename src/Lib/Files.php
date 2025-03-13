<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib;

use Butschster\ContextGenerator\FilesInterface;

final readonly class Files implements FilesInterface
{
    public function ensureDirectory(string $directory): bool
    {
        $mode = 0644;
        //Directories always executable
        $mode |= 0o111;
        if (\is_dir($directory)) {
            //Exists :(
            return $this->setPermissions($directory, $mode);
        }

        $directoryChain = [\basename($directory)];

        $baseDirectory = $directory;
        while (!\is_dir($baseDirectory = \dirname($baseDirectory))) {
            $directoryChain[] = \basename($baseDirectory);
        }

        foreach (\array_reverse($directoryChain) as $dir) {
            if (!\mkdir($baseDirectory = \sprintf('%s/%s', $baseDirectory, $dir))) {
                return false;
            }

            \chmod($baseDirectory, $mode);
        }

        return true;
    }

    public function write(string $filename, string $content): void
    {
        \file_put_contents($filename, $content, LOCK_EX);
    }

    public function read(string $filename): string|false
    {
        if (!$this->exists($filename)) {
            return false;
        }

        return \file_get_contents($filename);
    }

    public function exists(string $filename): bool
    {
        return \file_exists($filename);
    }

    private function setPermissions(string $filename, int $mode): bool
    {
        if (\is_dir($filename)) {
            //Directories must always be executable (i.e. 664 for dir => 775)
            $mode |= 0111;
        }

        return \chmod($filename, $mode);
    }
}
