<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Context7Client\Model;

final readonly class LibrarySearchResult implements \JsonSerializable
{
    /**
     * @param array<Library> $libraries List of libraries found
     */
    public function __construct(
        public int $count,
        public array $libraries,
    ) {}

    /**
     * Create search result from API response data
     */
    public static function fromArray(array $data, int $maxResults = 2): self
    {
        if (!isset($data['results']) || !\is_array(value: $data['results'])) {
            throw new \InvalidArgumentException(message: 'Invalid search response format: missing results array');
        }

        $libraries = [];
        $results = \array_slice(array: $data['results'], offset: 0, length: \min(5, \max(1, $maxResults)));

        foreach ($results as $libraryData) {
            $libraries[] = Library::fromArray(data: $libraryData);
        }

        return new self(
            count: \count(value: $libraries),
            libraries: $libraries,
        );
    }

    /**
     * Convert to array format for JSON responses
     */
    public function jsonSerialize(): array
    {
        return [
            'results' => $this->libraries,
        ];
    }
}
