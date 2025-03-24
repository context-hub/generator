<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\TokenCounter;

final readonly class CharTokenCounter implements TokenCounterInterface
{
    public function countFile(string $filePath): int
    {
        if (\is_dir($filePath)) {
            return 0;
        }

        if (!\file_exists($filePath) || !\is_readable($filePath)) {
            return 0;
        }

        $content = \file_get_contents($filePath);

        return $content === false ? 0 : \mb_strlen($content);
    }

    public function calculateDirectoryCount(array $directory): int
    {
        $totalChars = 0;

        foreach ($directory as $name => $children) {
            if (\is_array($children)) {
                $totalChars += $this->calculateDirectoryCount($children);
            } else {
                $totalChars += $this->countFile($children);
            }
        }

        return $totalChars;
    }
}
