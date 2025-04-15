<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Butschster\ContextGenerator\McpServer\Prompt\PromptType;
use Tests\Feature\FeatureTestCases;

/**
 * Tests template-related prompt functionality.
 */
final class TemplatePromptTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Prompts/templates.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Test base templates
        $baseTemplate = $config->getPrompts()->get('base-template');
        $this->assertInstanceOf(PromptDefinition::class, $baseTemplate);
        $this->assertSame(PromptType::Template, $baseTemplate->type);
        $this->assertCount(2, $baseTemplate->messages);
        $this->assertStringContainsString('{{context}}', $baseTemplate->messages[0]->content->text);

        $greetingTemplate = $config->getPrompts()->get('greeting-template');
        $this->assertInstanceOf(PromptDefinition::class, $greetingTemplate);
        $this->assertSame(PromptType::Template, $greetingTemplate->type);
        $this->assertCount(2, $greetingTemplate->messages);
        $this->assertStringContainsString('Hello, I\'m Template Test.', $greetingTemplate->messages[0]->content->text);

        // Test extended prompt (single template)
        $extendedPrompt = $config->getPrompts()->get('extended-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $extendedPrompt);
        $this->assertSame(PromptType::Prompt, $extendedPrompt->type);
        $this->assertCount(1, $extendedPrompt->extensions);
        $this->assertSame('base-template', $extendedPrompt->extensions[0]->templateId);

        // The messages should be resolved with variables replaced
        $this->assertCount(2, $extendedPrompt->messages);
        $this->assertStringContainsString('PHP development', $extendedPrompt->messages[0]->content->text);
        $this->assertStringContainsString('PHP development', $extendedPrompt->messages[1]->content->text);

        // Test multi-extension prompt
        $multiExtendedPrompt = $config->getPrompts()->get('multi-extended-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $multiExtendedPrompt);

        // The second extension should take precedence for messages
        $this->assertCount(4, $multiExtendedPrompt->messages);
        // Check that variables from both templates were properly resolved
        $this->assertStringContainsString('Developer', $multiExtendedPrompt->messages[2]->content->text);
        $this->assertStringContainsString('Developer', $multiExtendedPrompt->messages[3]->content->text);
    }
}
