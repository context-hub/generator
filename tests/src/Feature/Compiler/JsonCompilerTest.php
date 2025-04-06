<?php

declare(strict_types=1);

namespace Tests\Feature\Compiler;

use PHPUnit\Framework\Attributes\Group;

#[Group('compiler')]
#[Group('compiler-json')]
final class JsonCompilerTest extends AbstractCompilerTestCase
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Compiler/context.json');
    }
}
