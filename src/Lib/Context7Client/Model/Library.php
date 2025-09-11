<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Context7Client\Model;

final readonly class Library implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $branch = 'main',
        public string $lastUpdateDate = '',
        public int $totalTokens = 0,
        public int $totalPages = 0,
    ) {}

    /**
     * Create a Library instance from API response data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            title: $data['title'] ?? '',
            description: $data['description'] ?? '',
            branch: $data['branch'] ?? 'main',
            lastUpdateDate: $data['lastUpdateDate'] ?? '',
            totalTokens: $data['totalTokens'] ?? 0,
            totalPages: $data['totalPages'] ?? 0,
        );
    }

    /**
     * Get the usage instruction for this library
     */
    public function getUsage(): string
    {
        return \sprintf(
            "Use in your context config: { type: 'docs', library: '%s', topic: 'your-topic' }",
            \ltrim($this->id, '/'),
        );
    }

    /**
     * Convert to array format for API responses
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'usage' => $this->getUsage(),
        ];
    }
}
