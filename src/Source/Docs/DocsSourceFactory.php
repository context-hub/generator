<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Docs;

use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;

/**
 * Factory for creating DocsSource instances
 */
final readonly class DocsSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'docs';
    }

    #[\Override]
    public function create(array $config): DocsSource
    {
        $this->logger?->debug('Creating Docs source', [
            'path' => $this->dirs->getRootPath(),
            'config' => $config,
        ]);

        if (!isset($config['library']) || !\is_string($config['library'])) {
            throw new \RuntimeException('Docs source must have a "library" string property');
        }

        if (!isset($config['topic']) || !\is_string($config['topic'])) {
            throw new \RuntimeException('Docs source must have a "topic" string property');
        }

        $tokens = 2000;
        if (isset($config['tokens']) && (\is_int($config['tokens']) || \is_string($config['tokens']))) {
            $tokens = (int) $config['tokens'];
        }

        return new DocsSource(
            library: $config['library'],
            topic: $config['topic'],
            description: $config['description'] ?? '',
            tokens: $tokens,
            tags: $config['tags'] ?? [],
        );
    }
}
