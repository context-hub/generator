<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier;

/**
 * Data Transfer Object for source modifiers.
 * Contains modifier name and context information.
 */
final readonly class Modifier implements \JsonSerializable
{
    /**
     * Create a new modifier DTO.
     *
     * @param string $name The name/identifier of the modifier
     * @param array<string, mixed> $context Additional context/options for the modifier
     */
    public function __construct(
        public string $name,
        public array $context = [],
    ) {}

    /**
     * Create a ModifierDto from various formats:
     * - string: treated as modifier name with empty context
     * - array with 'name' key: treated as name with options in 'options' key
     * - ModifierDto: returned as is
     *
     * @param string|array<string, mixed>|self $data
     * @throws \InvalidArgumentException When input format is invalid
     */
    public static function from(string|array|self $data): self
    {
        // If already a ModifierDto instance, return it directly
        if ($data instanceof self) {
            return $data;
        }

        // If string, treat as modifier name with empty context
        if (\is_string($data)) {
            return new self(name: $data);
        }

        // If array, extract name and context
        if (isset($data['name'])) {
            $name = $data['name'];
            $context = $data['options'] ?? [];

            return new self(name: $name, context: $context);
        }

        throw new \InvalidArgumentException(
            'Invalid modifier format. Expected string, array with "name" key, or ModifierDto instance.',
        );
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = ['name' => $this->name];

        if (!empty($this->context)) {
            $result['options'] = $this->context;
        }

        return $result;
    }
}
