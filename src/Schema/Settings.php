<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Schema;

use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Schema\Attribute\SchemaType;

/**
 * Global settings
 */
#[SchemaType(name: 'settings', description: 'Global settings for Context Generator')]
final readonly class Settings implements \JsonSerializable
{
    /**
     * @param array<string, Modifier> $modifiers Named modifier configurations that can be referenced by alias
     */
    public function __construct(
        public array $modifiers = [],
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'modifiers' => $this->modifiers,
        ];
    }
}
