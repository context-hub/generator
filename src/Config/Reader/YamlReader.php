<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Exception\ReaderException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reader for YAML configuration files
 */
#[LoggerPrefix(prefix: 'yaml-reader')]
final readonly class YamlReader extends AbstractReader
{
    public function getSupportedExtensions(): array
    {
        return ['yaml', 'yml'];
    }

    protected function parseContent(string $content): array
    {
        try {
            if (!\class_exists(Yaml::class)) {
                throw new ReaderException(
                    'Symfony Yaml component is required to parse YAML files. Please install symfony/yaml package.',
                );
            }

            return (array) Yaml::parse($content);
        } catch (ParseException $e) {
            throw new ReaderException('Invalid YAML in configuration file', previous: $e);
        }
    }
}
