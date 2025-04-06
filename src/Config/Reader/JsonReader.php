<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Exception\ReaderException;

/**
 * Reader for JSON configuration files
 */
#[LoggerPrefix(prefix: 'json-reader')]
final readonly class JsonReader extends AbstractReader
{
    public function getSupportedExtensions(): array
    {
        return ['json'];
    }

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
}
