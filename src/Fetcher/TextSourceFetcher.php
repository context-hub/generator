<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Butschster\ContextGenerator\Source\TextSource;
use Butschster\ContextGenerator\SourceInterface;

/**
 * Fetcher for text sources
 * @implements SourceFetcherInterface<TextSource>
 */
final class TextSourceFetcher implements SourceFetcherInterface
{
    public function supports(SourceInterface $source): bool
    {
        return $source instanceof TextSource;
    }

    /**
     * @return string
     */
    public function fetch(SourceInterface $source): string
    {
        if (!$source instanceof TextSource) {
            throw new \InvalidArgumentException('Source must be an instance of TextSource');
        }

        return "// TEXT CONTENT:" . PHP_EOL .
            $source->content . PHP_EOL . PHP_EOL .
            "// END OF TEXT CONTENT" . PHP_EOL .
            '----------------------------------------------------------' . PHP_EOL;
    }
}
