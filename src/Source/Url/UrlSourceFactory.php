<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Url;

use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;

/**
 * Factory for creating UrlSource instances
 */
final readonly class UrlSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'url';
    }

    #[\Override]
    public function create(array $config): UrlSource
    {
        $this->logger?->debug('Creating URL source', [
            'path' => $this->dirs->getRootPath(),
            'config' => $config,
        ]);


        if (!isset($config['urls']) || !\is_array(value: $config['urls'])) {
            throw new \RuntimeException(message: 'URL source must have a "urls" array property');
        }

        // Add headers validation and parsing
        $headers = [];
        if (isset($config['headers']) && \is_array(value: $config['headers'])) {
            $headers = $config['headers'];
        }

        return new UrlSource(
            urls: $config['urls'],
            description: $config['description'] ?? '',
            headers: $headers,
            selector: $config['selector'] ?? null,
            tags: $config['tags'] ?? [],
        );
    }
}
