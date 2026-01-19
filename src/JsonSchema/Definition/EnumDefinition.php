<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Definition;

final class EnumDefinition implements DefinitionInterface
{
    private ?string $description = null;

    /**
     * @param array<string|int> $values
     */
    private function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly array $values,
    ) {}

    /**
     * @param array<string> $values
     */
    public static function string(string $name, array $values): self
    {
        return new self($name, 'string', $values);
    }

    /**
     * @param array<int> $values
     */
    public static function integer(string $name, array $values): self
    {
        return new self($name, 'integer', $values);
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $schema = [
            'type' => $this->type,
            'enum' => $this->values,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        return $schema;
    }

    public function getReferences(): array
    {
        return [];
    }
}
