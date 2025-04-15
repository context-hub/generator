<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Butschster\ContextGenerator\McpServer\Prompt\PromptRegistry;
use Butschster\ContextGenerator\McpServer\Prompt\PromptType;
use Tests\Feature\FeatureTestCases;

/**
 * Tests operations on the PromptRegistry.
 */
final class RegistryOperationsTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Prompts/registry_operations.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $registry = $config->getPrompts();
        $this->assertInstanceOf(PromptRegistry::class, $registry);

        // Test has() method
        $this->assertTrue($registry->has('prompt-one'), 'Registry should have prompt-one');
        $this->assertTrue($registry->has('template-one'), 'Registry should have template-one');
        $this->assertFalse($registry->has('non-existent-prompt'), 'Registry should not have non-existent-prompt');

        // Test get() method
        $promptOne = $registry->get('prompt-one');
        $this->assertInstanceOf(PromptDefinition::class, $promptOne);
        $this->assertSame('prompt-one', $promptOne->id);

        $templateOne = $registry->get('template-one');
        $this->assertInstanceOf(PromptDefinition::class, $templateOne);
        $this->assertSame('template-one', $templateOne->id);

        // Test get() with invalid prompt
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('non-existent-prompt');

        // Restore expectations for the rest of the test
        $this->expectNotToPerformAssertions();

        // Test all() method
        $allPrompts = $registry->all();
        $this->assertCount(5, $allPrompts, 'Registry should have 5 total prompts/templates');

        // Test allTemplates() method
        $templates = $registry->allTemplates();
        $this->assertCount(2, $templates, 'Registry should have 2 templates');

        foreach ($templates as $template) {
            $this->assertSame(PromptType::Template, $template->type, 'Templates should have Template type');
        }

        // Test getItems() method (should return only regular prompts, not templates)
        $items = $registry->getItems();
        $this->assertCount(3, $items, 'Registry should have 3 regular prompts');

        foreach ($items as $item) {
            $this->assertSame(PromptType::Prompt, $item->type, 'Items should have Prompt type');
        }

        // Test getType() method
        $this->assertSame('prompts', $registry->getType(), 'Registry type should be "prompts"');

        // Test getIterator() method
        $count = 0;
        foreach ($registry as $item) {
            $this->assertInstanceOf(PromptDefinition::class, $item);
            $this->assertSame(PromptType::Prompt, $item->type);
            $count++;
        }
        $this->assertSame(3, $count, 'Iterator should yield 3 items');
    }
}
