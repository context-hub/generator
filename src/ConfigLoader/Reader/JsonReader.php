<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Reader;

use Butschster\ContextGenerator\ConfigLoader\Exception\ReaderException;

/**
 * Reader for JSON configuration files
 */
final readonly class JsonReader extends AbstractReader
{
    protected function parseContent(string $content): array
    {
        try {
            $config = \json_decode($content, true, flags: JSON_THROW_ON_ERROR);

            if (!\is_array($config)) {
                throw new ReaderException('JSON configuration must decode to an array');
            }

            return $config;
        } catch (\JsonException $e) {
            throw new ReaderException('Invalid JSON in configuration file', previous: $e);
        }
    }

    protected function getSupportedExtensions(): array
    {
        return ['json'];
    }
}
