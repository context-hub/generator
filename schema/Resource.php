<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

class Resource implements \JsonSerializable
{
    /**
     * URI pattern regex - requires a valid scheme, followed by colon and optional path.
     * Example patterns: config://, file://path, db://table, etc.
     */
    private const URI_PATTERN = '/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/[^\s]*$/';

    /**
     * @param string $uri The URI of this resource.
     * @param string $name A human-readable name for this resource. This can be used by clients to populate UI elements.
     * @param string|null $description A description of what this resource represents. This can be used by clients to improve the LLM's understanding of available resources. It can be thought of like a "hint" to the model.
     * @param string|null $mimeType The MIME type of this resource, if known.
     * @param Annotations|null $annotations Optional annotations for the client.
     * @param int|null $size The size of the raw resource content, in bytes (i.e., before base64 encoding or any tokenization), if known.
     *
     * This can be used by Hosts to display file sizes and estimate context window usage.
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $mimeType = null,
        public readonly ?Annotations $annotations = null,
        public readonly ?int $size = null,
    ) {
        if (!\preg_match(self::URI_PATTERN, $uri)) {
            throw new \InvalidArgumentException("Invalid resource URI: must be a valid URI with a scheme and optional path.");
        }
    }

    /**
     * @param string $uri The URI of this resource.
     * @param string $name A human-readable name for this resource. This can be used by clients to populate UI elements.
     * @param string|null $description A description of what this resource represents. This can be used by clients to improve the LLM's understanding of available resources. It can be thought of like a "hint" to the model.
     * @param string|null $mimeType The MIME type of this resource, if known.
     * @param Annotations|null $annotations Optional annotations for the client.
     * @param int|null $size The size of the raw resource content, in bytes (i.e., before base64 encoding or any tokenization), if known.
     *
     * This can be used by Hosts to display file sizes and estimate context window usage.
     */
    public static function make(string $uri, string $name, ?string $description = null, ?string $mimeType = null, ?Annotations $annotations = null, ?int $size = null): static
    {
        return new static($uri, $name, $description, $mimeType, $annotations, $size);
    }

    public function toArray(): array
    {
        $data = [
            'uri' => $this->uri,
            'name' => $this->name,
        ];
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }
        if ($this->annotations !== null) {
            $data['annotations'] = $this->annotations->toArray();
        }
        if ($this->size !== null) {
            $data['size'] = $this->size;
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (empty($data['uri']) || !\is_string($data['uri'])) {
            throw new \InvalidArgumentException("Invalid or missing 'uri' in Resource data.");
        }
        if (empty($data['name']) || !\is_string($data['name'])) {
            throw new \InvalidArgumentException("Invalid or missing 'name' in Resource data.");
        }
        return new static(
            uri: $data['uri'],
            name: $data['name'],
            description: $data['description'] ?? null,
            mimeType: $data['mimeType'] ?? null,
            annotations: isset($data['annotations']) ? Annotations::fromArray($data['annotations']) : null,
            size: isset($data['size']) ? (int) $data['size'] : null,
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
