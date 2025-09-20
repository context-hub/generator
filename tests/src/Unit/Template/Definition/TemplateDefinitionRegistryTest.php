<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Definition;

use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionInterface;
use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;
use Butschster\ContextGenerator\Template\Template;
use PHPUnit\Framework\TestCase;

class TemplateDefinitionRegistryTest extends TestCase
{
    public function testConstructorWithEmptyDefinitions(): void
    {
        $registry = new TemplateDefinitionRegistry();

        $this->assertSame([], $registry->createAllTemplates());
    }

    public function testConstructorWithDefinitions(): void
    {
        $definition1 = $this->createMockDefinition('template1', 'Template 1', 5);
        $definition2 = $this->createMockDefinition('template2', 'Template 2', 10);

        $registry = new TemplateDefinitionRegistry([$definition1, $definition2]);

        $templates = $registry->createAllTemplates();
        $this->assertCount(2, $templates);
    }

    public function testRegisterDefinition(): void
    {
        $registry = new TemplateDefinitionRegistry();
        $definition = $this->createMockDefinition('test', 'Test Template', 5);

        $registry->registerDefinition($definition);

        $templates = $registry->createAllTemplates();
        $this->assertCount(1, $templates);
        $this->assertSame('test', $templates[0]->name);
    }

    public function testDefinitionsAreSortedByPriority(): void
    {
        $registry = new TemplateDefinitionRegistry();

        $lowPriorityDefinition = $this->createMockDefinition('low', 'Low Priority', 1);
        $highPriorityDefinition = $this->createMockDefinition('high', 'High Priority', 10);
        $mediumPriorityDefinition = $this->createMockDefinition('medium', 'Medium Priority', 5);

        // Register in random order
        $registry->registerDefinition($lowPriorityDefinition);
        $registry->registerDefinition($highPriorityDefinition);
        $registry->registerDefinition($mediumPriorityDefinition);

        $templates = $registry->createAllTemplates();

        // Should be sorted by priority (highest first)
        $this->assertSame('high', $templates[0]->name);    // priority 10
        $this->assertSame('medium', $templates[1]->name);  // priority 5
        $this->assertSame('low', $templates[2]->name);     // priority 1
    }

    public function testGetDefinitionReturnsCorrectDefinition(): void
    {
        $definition1 = $this->createMockDefinition('template1', 'Template 1', 5);
        $definition2 = $this->createMockDefinition('template2', 'Template 2', 10);

        $registry = new TemplateDefinitionRegistry([$definition1, $definition2]);

        $result = $registry->getDefinition('template1');
        $this->assertSame($definition1, $result);

        $result = $registry->getDefinition('template2');
        $this->assertSame($definition2, $result);
    }

    public function testGetDefinitionReturnsNullWhenNotFound(): void
    {
        $definition = $this->createMockDefinition('existing', 'Existing Template', 5);
        $registry = new TemplateDefinitionRegistry([$definition]);

        $result = $registry->getDefinition('non-existent');
        $this->assertNull($result);
    }

    public function testCreateAllTemplatesPassesProjectMetadata(): void
    {
        $projectMetadata = ['files' => ['composer.json']];

        $definition = $this->createMock(TemplateDefinitionInterface::class);
        $definition->method('getName')->willReturn('test');
        $definition->method('getPriority')->willReturn(5);
        $definition->expects($this->once())
            ->method('createTemplate')
            ->with($projectMetadata)
            ->willReturn(new Template('test', 'Test Template'));

        $registry = new TemplateDefinitionRegistry([$definition]);

        $templates = $registry->createAllTemplates($projectMetadata);
        $this->assertCount(1, $templates);
    }

    public function testCreateTemplateWithExistingName(): void
    {
        $projectMetadata = ['files' => ['composer.json']];
        $expectedTemplate = new Template('test', 'Test Template');

        $definition = $this->createMock(TemplateDefinitionInterface::class);
        $definition->method('getName')->willReturn('test');
        $definition->method('getPriority')->willReturn(5);
        $definition->expects($this->once())
            ->method('createTemplate')
            ->with($projectMetadata)
            ->willReturn($expectedTemplate);

        $registry = new TemplateDefinitionRegistry([$definition]);

        $result = $registry->createTemplate('test', $projectMetadata);
        $this->assertSame($expectedTemplate, $result);
    }

    public function testCreateTemplateWithNonExistentName(): void
    {
        $definition = $this->createMockDefinition('existing', 'Existing Template', 5);
        $registry = new TemplateDefinitionRegistry([$definition]);

        $result = $registry->createTemplate('non-existent');
        $this->assertNull($result);
    }

    public function testCreateTemplateWithEmptyProjectMetadata(): void
    {
        $definition = $this->createMock(TemplateDefinitionInterface::class);
        $definition->method('getName')->willReturn('test');
        $definition->method('getPriority')->willReturn(5);
        $definition->expects($this->once())
            ->method('createTemplate')
            ->with([]) // Empty array as default
            ->willReturn(new Template('test', 'Test Template'));

        $registry = new TemplateDefinitionRegistry([$definition]);

        $result = $registry->createTemplate('test');
        $this->assertInstanceOf(Template::class, $result);
    }

    private function createMockDefinition(string $name, string $description, int $priority): TemplateDefinitionInterface
    {
        $definition = $this->createMock(TemplateDefinitionInterface::class);
        $definition->method('getName')->willReturn($name);
        $definition->method('getPriority')->willReturn($priority);
        $definition->method('createTemplate')->willReturn(new Template($name, $description, priority: $priority));

        return $definition;
    }
}
