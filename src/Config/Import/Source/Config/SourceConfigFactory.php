<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Config;

use Butschster\ContextGenerator\Config\Import\Source\Local\LocalSourceConfig;
use Butschster\ContextGenerator\Config\Import\Source\Url\UrlSourceConfig;

/**
 * Factory for creating source configurations from raw configuration arrays
 */
final class SourceConfigFactory
{
    /**
     * Create a source configuration from a raw configuration array
     *
     * @param array $config Raw configuration array
     * @param string $basePath Base path for resolving relative paths
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function createFromArray(array $config, string $basePath): SourceConfigInterface
    {
        $type = $config['type'] ?? 'local';

        return match ($type) {
            'local' => LocalSourceConfig::fromArray($config, $basePath),
            'url' => UrlSourceConfig::fromArray($config, $basePath),
            default => throw new \InvalidArgumentException("Unsupported source type: {$type}"),
        };
    }
}
