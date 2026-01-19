<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Property;

final class RefProperty implements PropertyInterface
{
    private bool $nullable = false;

    private function __construct(
        private readonly string $reference,
    ) {}

    public static function to(string $definition): self
    {
        return new self($definition);
    }

    /**
     * Makes this reference nullable (oneOf with null type).
     */
    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function toArray(): array
    {
        $ref = ['$ref' => "#/definitions/{$this->reference}"];

        if ($this->nullable) {
            return [
                'oneOf' => [
                    $ref,
                    ['type' => 'null'],
                ],
            ];
        }

        return $ref;
    }

    public function getReferences(): array
    {
        return [$this->reference];
    }
}
