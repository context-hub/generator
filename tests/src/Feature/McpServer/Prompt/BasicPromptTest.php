<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Mcp\Types\Role;
use Mcp\Types\TextContent;
use Tests\Feature\FeatureTestCases;

/**
 * Tests basic prompt parsing functionality.
 */
final class BasicPromptTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Prompts/basic_prompts.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Test minimal prompt
        $minimalPrompt = $config->getPrompts()->get('minimal-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $minimalPrompt);
        $this->assertSame('minimal-prompt', $minimalPrompt->id);
        $this->assertSame('A minimal prompt with just the required fields', $minimalPrompt->prompt->description);
        $this->assertCount(1, $minimalPrompt->messages);
        $this->assertSame(Role::USER, $minimalPrompt->messages[0]->role);
        $this->assertInstanceOf(TextContent::class, $minimalPrompt->messages[0]->content);
        $this->assertSame('You are a helpful assistant.', $minimalPrompt->messages[0]->content->text);

        // Test multi-message prompt
        $multiMessagePrompt = $config->getPrompts()->get('multi-message-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $multiMessagePrompt);
        $this->assertCount(3, $multiMessagePrompt->messages);
        $this->assertSame(Role::USER, $multiMessagePrompt->messages[0]->role);
        $this->assertSame(Role::ASSISTANT, $multiMessagePrompt->messages[1]->role);
        $this->assertSame(Role::USER, $multiMessagePrompt->messages[2]->role);
        $this->assertSame('Tell me about context generation.', $multiMessagePrompt->messages[2]->content->text);

        // Test variable prompt
        $variablePrompt = $config->getPrompts()->get('variable-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $variablePrompt);
        $this->assertCount(2, $variablePrompt->messages);

        // Variables should already be resolved in the messages
        $this->assertStringContainsString('Basic Prompt Test', $variablePrompt->messages[0]->content->text);
        $this->assertStringContainsString('Basic Prompt Test', $variablePrompt->messages[1]->content->text);
    }
}
