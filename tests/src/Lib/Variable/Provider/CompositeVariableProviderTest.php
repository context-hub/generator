<?php

declare(strict_types=1);

namespace Tests\Lib\Variable\Provider;

use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompositeVariableProvider::class)]
class CompositeVariableProviderTest extends TestCase
{
    #[Test]
    public function it_should_return_false_when_no_providers_added(): void
    {
        $provider = new CompositeVariableProvider();

        $this->assertFalse($provider->has('TEST_VAR'));
        $this->assertNull($provider->get('TEST_VAR'));
    }

    #[Test]
    public function it_should_check_all_providers_until_one_returns_true(): void
    {
        // Create mock providers
        $provider1 = $this->createMock(VariableProviderInterface::class);
        $provider1->method('has')->with('TEST_VAR')->willReturn(false);

        $provider2 = $this->createMock(VariableProviderInterface::class);
        $provider2->method('has')->with('TEST_VAR')->willReturn(true);

        $provider3 = $this->createMock(VariableProviderInterface::class);
        // This should never be called if provider2 returns true
        $provider3->expects($this->never())->method('has');

        $composite = new CompositeVariableProvider($provider1, $provider2, $provider3);

        $this->assertTrue($composite->has('TEST_VAR'));
    }

    #[Test]
    public function it_should_return_value_from_first_provider_that_has_variable(): void
    {
        // Create mock providers
        $provider1 = $this->createMock(VariableProviderInterface::class);
        $provider1->method('has')->with('TEST_VAR')->willReturn(false);
        $provider1->expects($this->never())->method('get');

        $provider2 = $this->createMock(VariableProviderInterface::class);
        $provider2->method('has')->with('TEST_VAR')->willReturn(true);
        $provider2->method('get')->with('TEST_VAR')->willReturn('test_value');

        $provider3 = $this->createMock(VariableProviderInterface::class);
        // These should never be called if provider2 has the variable
        $provider3->expects($this->never())->method('has');
        $provider3->expects($this->never())->method('get');

        $composite = new CompositeVariableProvider($provider1, $provider2, $provider3);

        $this->assertSame('test_value', $composite->get('TEST_VAR'));
    }

    #[Test]
    public function it_should_respect_provider_priority(): void
    {
        // Create mock providers
        $lowPriority = $this->createMock(VariableProviderInterface::class);
        $lowPriority->method('has')->with('SHARED_VAR')->willReturn(true);
        $lowPriority->method('get')->with('SHARED_VAR')->willReturn('low_priority_value');

        $highPriority = $this->createMock(VariableProviderInterface::class);
        $highPriority->method('has')->with('SHARED_VAR')->willReturn(true);
        $highPriority->method('get')->with('SHARED_VAR')->willReturn('high_priority_value');

        // Create composite provider with low priority provider first
        $composite = new CompositeVariableProvider($lowPriority);

        // Add high priority provider
        $composite->addProviderWithHighPriority($highPriority);

        // The high priority provider should be checked first
        $this->assertSame('high_priority_value', $composite->get('SHARED_VAR'));
    }

    #[Test]
    public function it_should_add_providers_correctly(): void
    {
        $provider1 = $this->createMock(VariableProviderInterface::class);
        $provider2 = $this->createMock(VariableProviderInterface::class);
        $provider3 = $this->createMock(VariableProviderInterface::class);

        $composite = new CompositeVariableProvider($provider1);
        $composite->addProvider($provider2);
        $composite->addProviderWithHighPriority($provider3);

        $providers = $composite->getProviders();

        $this->assertCount(3, $providers);
        $this->assertSame($provider3, $providers[0]); // High priority should be first
        $this->assertSame($provider1, $providers[1]);
        $this->assertSame($provider2, $providers[2]);
    }
}
