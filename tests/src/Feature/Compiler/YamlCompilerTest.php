<?php

declare(strict_types=1);

namespace Tests\Feature\Compiler;

use PHPUnit\Framework\Attributes\Group;

#[Group('compiler')]
#[Group('compiler-yaml')]
final class YamlCompilerTest extends AbstractCompilerTestCase
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Compiler/context.yaml');
    }
}
