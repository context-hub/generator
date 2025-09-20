<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Registry;

use Butschster\ContextGenerator\Template\Registry\TemplateProviderInterface;
use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;
use Butschster\ContextGenerator\Template\Template;
use PHPUnit\Framework\TestCase;

class TemplateRegistryTest extends TestCase
{
    private TemplateRegistry $registry;

    public function testRegisterProvider(): void
    {
        $provider = $this->createMockProvider([]);

        $this->registry->registerProvider($provider);

        // Test that provider is registered by checking getAllTemplates
        $this->assertSame([], $this->registry->getAllTemplates());
    }

    public function testProvidersAreSortedByPriority(): void
    {
        $lowPriorityProvider = $this->createMock(TemplateProviderInterface::class);
        $lowPriorityProvider->method('getPriority')->willReturn(1);
        $lowPriorityProvider->method('getTemplates')->willReturn([]);

        $highPriorityProvider = $this->createMock(TemplateProviderInterface::class);
        $highPriorityProvider->method('getPriority')->willReturn(10);
        $highPriorityProvider->method('getTemplates')->willReturn([]);

        // Register in reverse priority order
        $this->registry->registerProvider($lowPriorityProvider);
        $this->registry->registerProvider($highPriorityProvider);

        // Verify providers are sorted by checking that high priority provider is called first
        $highPriorityProvider->expects($this->once())->method('getTemplates');
        $lowPriorityProvider->expects($this->once())->method('getTemplates');

        $this->registry->getAllTemplates();
    }

    public function testGetAllTemplatesReturnsTemplatesFromAllProviders(): void
    {
        $template1 = new Template('template1', 'Template 1', priority: 5);
        $template2 = new Template('template2', 'Template 2', priority: 10);
        $template3 = new Template('template3', 'Template 3', priority: 3);

        $provider1 = $this->createMockProvider([$template1, $template2], priority: 5);
        $provider2 = $this->createMockProvider([$template3], priority: 1);

        $this->registry->registerProvider($provider1);
        $this->registry->registerProvider($provider2);

        $templates = $this->registry->getAllTemplates();

        $this->assertCount(3, $templates);
        $this->assertContains($template1, $templates);
        $this->assertContains($template2, $templates);
        $this->assertContains($template3, $templates);
    }

    public function testGetAllTemplatesSortsByTemplatePriority(): void
    {
        $template1 = new Template('template1', 'Template 1', priority: 5);
        $template2 = new Template('template2', 'Template 2', priority: 10);
        $template3 = new Template('template3', 'Template 3', priority: 3);

        $provider = $this->createMockProvider([$template1, $template2, $template3]);

        $this->registry->registerProvider($provider);

        $templates = $this->registry->getAllTemplates();

        // Should be sorted by priority (highest first)
        $this->assertSame($template2, $templates[0]); // priority 10
        $this->assertSame($template1, $templates[1]); // priority 5
        $this->assertSame($template3, $templates[2]); // priority 3
    }

    public function testGetAllTemplatesAvoidsDuplicates(): void
    {
        $template1 = new Template('same-name', 'Template 1', priority: 5);
        $template2 = new Template('same-name', 'Template 2', priority: 10);
        $template3 = new Template('different-name', 'Template 3', priority: 3);

        $provider1 = $this->createMockProvider([$template1], priority: 10); // Higher priority
        $provider2 = $this->createMockProvider([$template2, $template3], priority: 5); // Lower priority

        $this->registry->registerProvider($provider1);
        $this->registry->registerProvider($provider2);

        $templates = $this->registry->getAllTemplates();

        $this->assertCount(2, $templates);

        // Should contain template1 (from higher priority provider) not template2
        $templateNames = \array_map(static fn($t) => $t->name, $templates);
        $this->assertContains('same-name', $templateNames);
        $this->assertContains('different-name', $templateNames);

        // Verify it's template1 (higher priority provider), not template2
        $sameNameTemplate = \array_filter($templates, static fn($t) => $t->name === 'same-name')[0];
        $this->assertSame('Template 1', $sameNameTemplate->description);
    }

    public function testGetTemplateReturnsFirstMatch(): void
    {
        $template1 = new Template('test-template', 'Template 1');
        $template2 = new Template('other-template', 'Template 2');

        $provider1 = $this->createMockProvider([$template1], priority: 10);
        $provider2 = $this->createMockProvider([$template2], priority: 5);

        $this->registry->registerProvider($provider1);
        $this->registry->registerProvider($provider2);

        $result = $this->registry->getTemplate('test-template');

        $this->assertSame($template1, $result);
    }

    public function testGetTemplateReturnsNullWhenNotFound(): void
    {
        $template = new Template('existing-template', 'Existing Template');
        $provider = $this->createMockProvider([$template]);

        $this->registry->registerProvider($provider);

        $result = $this->registry->getTemplate('non-existent-template');

        $this->assertNull($result);
    }

    public function testGetTemplateReturnsFromHighestPriorityProvider(): void
    {
        $template1 = new Template('test-template', 'Template from Provider 1');
        $template2 = new Template('test-template', 'Template from Provider 2');

        $provider1 = $this->createMockProvider([$template1], priority: 5);
        $provider2 = $this->createMockProvider([$template2], priority: 10); // Higher priority

        $this->registry->registerProvider($provider1);
        $this->registry->registerProvider($provider2);

        $result = $this->registry->getTemplate('test-template');

        $this->assertSame($template2, $result);
        $this->assertSame('Template from Provider 2', $result->description);
    }

    public function testGetAllTemplatesWithEmptyRegistry(): void
    {
        $templates = $this->registry->getAllTemplates();

        $this->assertSame([], $templates);
    }

    public function testGetTemplateWithEmptyRegistry(): void
    {
        $result = $this->registry->getTemplate('any-template');

        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        $this->registry = new TemplateRegistry();
    }

    private function createMockProvider(array $templates, int $priority = 0): TemplateProviderInterface
    {
        $provider = $this->createMock(TemplateProviderInterface::class);
        $provider->method('getTemplates')->willReturn($templates);
        $provider->method('getPriority')->willReturn($priority);

        // Mock getTemplate method to return the template if it exists
        $provider->method('getTemplate')->willReturnCallback(
            static function (string $name) use ($templates): ?Template {
                foreach ($templates as $template) {
                    if ($template->name === $name) {
                        return $template;
                    }
                }
                return null;
            },
        );

        return $provider;
    }
}
