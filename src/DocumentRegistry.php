<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

final class DocumentRegistry implements \JsonSerializable
{
    /**
     * @var array<Document>
     */
    private array $documents = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Register a document in the registry
     */
    public function register(Document $document): self
    {
        $this->documents[] = $document;

        return $this;
    }

    /**
     * Get all registered documents
     *
     * @return array<Document>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function jsonSerialize(): array
    {
        return [
            'documents' => $this->documents,
        ];
    }
}
