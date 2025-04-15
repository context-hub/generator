<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Tests\Feature\FeatureTestCases;

/**
 * Tests schema-related prompt functionality.
 */
final class SchemaPromptTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Prompts/prompt_schema.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Test basic schema prompt
        $schemaPrompt = $config->getPrompts()->get('schema-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $schemaPrompt);

        // Verify schema properties
        $this->assertCount(2, $schemaPrompt->prompt->arguments);

        // Check first argument - language
        $languageArg = null;
        $experienceArg = null;

        foreach ($schemaPrompt->prompt->arguments as $arg) {
            if ($arg->name === 'language') {
                $languageArg = $arg;
            } elseif ($arg->name === 'experience') {
                $experienceArg = $arg;
            }
        }

        $this->assertNotNull($languageArg, 'Language argument not found');
        $this->assertNotNull($experienceArg, 'Experience argument not found');

        $this->assertSame('Programming language to use', $languageArg->description);
        $this->assertTrue($languageArg->required, 'Language argument should be required');

        $this->assertSame('User experience level', $experienceArg->description);
        $this->assertFalse($experienceArg->required, 'Experience argument should not be required');

        // Verify messages with variable placeholders
        $this->assertCount(2, $schemaPrompt->messages);
        $this->assertStringContainsString('{{language}}', $schemaPrompt->messages[0]->content->text);
        $this->assertStringContainsString('{{experience}}', $schemaPrompt->messages[1]->content->text);

        // Test complex schema prompt
        $complexSchemaPrompt = $config->getPrompts()->get('complex-schema-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $complexSchemaPrompt);

        // Verify schema properties
        $this->assertCount(4, $complexSchemaPrompt->prompt->arguments);

        // Check the required arguments
        $requiredArgs = \array_filter(
            $complexSchemaPrompt->prompt->arguments,
            static fn($arg) => $arg->required,
        );

        $this->assertCount(2, $requiredArgs, 'Should have 2 required arguments');

        // Verify that all expected argument names exist
        $argNames = \array_map(
            static fn($arg) => $arg->name,
            $complexSchemaPrompt->prompt->arguments,
        );

        $this->assertContains('project_name', $argNames);
        $this->assertContains('framework', $argNames);
        $this->assertContains('features', $argNames);
        $this->assertContains('database', $argNames);

        // Verify messages with variable placeholders
        $this->assertCount(1, $complexSchemaPrompt->messages);
        $this->assertStringContainsString('{{project_name}}', $complexSchemaPrompt->messages[0]->content->text);
        $this->assertStringContainsString('{{framework}}', $complexSchemaPrompt->messages[0]->content->text);
    }
}
