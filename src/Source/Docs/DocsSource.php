<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Docs;

use Butschster\ContextGenerator\Source\BaseSource;

/**
 * Source for retrieving documentation from Context7 service
 */
final class DocsSource extends BaseSource
{
    /**
     * @param string $library Library identifier (e.g., "laravel/docs")
     * @param string $topic Topic to search for within the library
     * @param string $description Human-readable description
     * @param int $tokens Maximum token count to retrieve
     * @param array<non-empty-string> $tags
     */
    public function __construct(
        public readonly string $library,
        public readonly string $topic,
        string $description = '',
        public readonly int $tokens = 2000,
        array $tags = [],
    ) {
        parent::__construct(description: $description, tags: $tags);
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'docs',
            ...parent::jsonSerialize(),
            'library' => $this->library,
            'topic' => $this->topic,
            'tokens' => $this->tokens,
        ], static fn($value) => $value !== null && $value !== '' && $value !== []);
    }
}
