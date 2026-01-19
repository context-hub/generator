<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

final class Conditional implements CombinatorInterface
{
    private ?PropertyInterface $then = null;
    private ?PropertyInterface $else = null;

    /**
     * @param array<string, mixed> $if
     */
    private function __construct(
        private readonly array $if,
    ) {}

    /**
     * Create condition: if property equals value.
     */
    public static function when(string $property, mixed $equals): self
    {
        return new self([
            'properties' => [
                $property => ['const' => $equals],
            ],
        ]);
    }

    /**
     * Create condition with custom if schema.
     *
     * @param array<string, mixed> $condition
     */
    public static function if(array $condition): self
    {
        return new self($condition);
    }

    public function then(PropertyInterface $schema): self
    {
        $this->then = $schema;
        return $this;
    }

    public function else(PropertyInterface $schema): self
    {
        $this->else = $schema;
        return $this;
    }

    public function toArray(): array
    {
        $schema = ['if' => $this->if];

        if ($this->then !== null) {
            $schema['then'] = $this->then->toArray();
        }

        if ($this->else !== null) {
            $schema['else'] = $this->else->toArray();
        }

        return $schema;
    }

    public function getReferences(): array
    {
        $refs = [];

        if ($this->then !== null) {
            $refs = [...$refs, ...$this->then->getReferences()];
        }

        if ($this->else !== null) {
            $refs = [...$refs, ...$this->else->getReferences()];
        }

        return \array_values(\array_unique($refs));
    }
}
