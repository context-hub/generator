<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Text;

use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Factory for creating TextSource instances
 */
final readonly class TextSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'text';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating text source', [
            'config' => $config,
        ]);

        if (!isset($config['content']) || !\is_string($config['content'])) {
            throw new \RuntimeException('Text source must have a "content" string property');
        }

        return new TextSource(
            content: $config['content'],
            description: $config['description'] ?? '',
            tag: $config['tag'] ?? 'INSTRUCTION',
            tags: $config['tags'] ?? [],
        );
    }
}
