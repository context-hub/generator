<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier;

final readonly class ModifierRegistryFactory
{
    public function create(): SourceModifierRegistry
    {
        $modifiers = new SourceModifierRegistry();
        $modifiers->register(
            new PhpSignature(),
            new ContextSanitizerModifier(),
            new PhpContentFilter(),
            new AstDocTransformer(),
        );

        return $modifiers;
    }
}
