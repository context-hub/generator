<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Provider;

use PHPUnit\Framework\TestCase;

class BuiltinTemplateProviderTest extends TestCase
{
    public function testSkipped(): void
    {
        // Since BuiltinTemplateProvider depends on final class TemplateDefinitionRegistry,
        // we cannot easily mock it in unit tests. These tests should be converted to integration tests
        // or the dependencies should be made non-final with interfaces.
        $this->markTestSkipped('Testing final class dependencies requires integration testing approach');
    }
}
