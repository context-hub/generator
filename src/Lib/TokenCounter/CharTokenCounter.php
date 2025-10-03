<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TokenCounter;

final readonly class CharTokenCounter implements TokenCounterInterface
{
    public function countFile(string $filePath): int
    {
        if (\is_dir(filename: $filePath)) {
            return 0;
        }

        if (!\file_exists(filename: $filePath) || !\is_readable(filename: $filePath)) {
            return 0;
        }

        $content = \file_get_contents(filename: $filePath);

        return $content === false ? 0 : \mb_strlen(string: $content);
    }

    public function calculateDirectoryCount(array $directory): int
    {
        $totalChars = 0;

        foreach ($directory as $children) {
            if (\is_array(value: $children)) {
                $totalChars += $this->calculateDirectoryCount(directory: $children);
            } else {
                $totalChars += $this->countFile(filePath: $children);
            }
        }

        return $totalChars;
    }
}
