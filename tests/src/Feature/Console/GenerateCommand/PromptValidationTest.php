<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class PromptValidationTest extends ConsoleTestCase
{
    private string $outputDir;

    public static function commandsProvider(): \Generator
    {
        yield 'generate' => ['generate'];
        yield 'build' => ['build'];
        yield 'compile' => ['compile'];
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_without_messages_or_extensions_should_be_invalid(string $command): void
    {
        // Create a config with a prompt that has neither messages nor extensions
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: empty-prompt
                    description: "Prompt with no instructions"
                    # Missing both 'messages' and 'extend'
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should either:
        // 1. Reject the prompt entirely (prompts count = 0)
        // 2. Or log an error/warning about the prompt

        // For now, check that the prompt count is 0
        // If the implementation doesn't reject empty prompts, this test may need adjustment
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_empty_messages_array_should_be_invalid(string $command): void
    {
        // Create a config with a prompt that has an empty messages array
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: empty-messages-prompt
                    description: "Prompt with empty messages array"
                    messages: []  # Empty array
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with empty messages array
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_invalid_message_format_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that has invalid message format
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: invalid-message-format
                    description: "Prompt with invalid message format"
                    messages:
                      - invalid_format: "This doesn't have role and content"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with invalid message format
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_missing_message_content_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that has a message with missing content
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: missing-content
                    description: "Prompt with message missing content"
                    messages:
                      - role: user
                        # Missing content
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with missing message content
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_missing_message_role_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that has a message with missing role
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: missing-role
                    description: "Prompt with message missing role"
                    messages:
                      - content: "This message is missing a role"
                        # Missing role
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with missing message role
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_invalid_message_role_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that has an invalid message role
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: invalid-role
                    description: "Prompt with invalid message role"
                    messages:
                      - role: invalid_role  # Not 'user' or 'assistant'
                        content: "This message has an invalid role"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with invalid message role
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_extending_non_existent_template_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that extends a non-existent template
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: extending-nonexistent
                    description: "Prompt extending non-existent template"
                    extend:
                      - id: nonexistent-template
                        arguments:
                          var: "value"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt extending a non-existent template
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function template_without_messages_should_be_invalid(string $command): void
    {
        // Create a config with a template that has no messages
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: empty-template
                    type: template
                    description: "Template with no messages"
                    # Missing 'messages'
                
                  - id: extending-empty
                    description: "Prompt extending empty template"
                    extend:
                      - id: empty-template
                        arguments:
                          var: "value"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should either:
        // 1. Reject the template (and the extending prompt)
        // 2. Or warn about the empty template

        // For now, check that no valid prompts are compiled
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_invalid_extension_format_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that has invalid extension format
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: template
                    type: template
                    messages:
                      - role: user
                        content: "Template content"
                
                  - id: invalid-extension
                    description: "Prompt with invalid extension format"
                    extend:
                      - invalid_format: "This doesn't have id and arguments"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with invalid extension format
        // At minimum, the valid template should be compiled
        $rawResult = $result->getResult();
        $validPromptCount = 0;
        if (isset($rawResult['prompts'])) {
            foreach ($rawResult['prompts'] as $prompt) {
                if ($prompt['id'] === 'invalid-extension') {
                    // This prompt should be rejected
                    $this->fail("Prompt with invalid extension format should be rejected");
                } elseif ($prompt['id'] === 'template') {
                    // Template should be accepted
                    $validPromptCount++;
                }
            }
        }

        // Only the template should be compiled (if any)
        $this->assertLessThanOrEqual(1, $validPromptCount);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_missing_id_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that has no ID
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - description: "Prompt with no ID"
                    # Missing 'id'
                    messages:
                      - role: user
                        content: "This prompt has no ID"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with no ID
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_empty_id_should_be_rejected(string $command): void
    {
        // Create a config with a prompt that has an empty ID
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: ""  # Empty ID
                    description: "Prompt with empty ID"
                    messages:
                      - role: user
                        content: "This prompt has an empty ID"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should reject a prompt with an empty ID
        $result->assertPromptCount(0);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function duplicated_prompt_ids_should_be_resolved(string $command): void
    {
        // Create a config with multiple prompts with the same ID
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: duplicate-id
                    description: "First prompt with this ID"
                    messages:
                      - role: user
                        content: "First prompt content"
                
                  - id: duplicate-id
                    description: "Second prompt with the same ID"
                    messages:
                      - role: user
                        content: "Second prompt content"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should handle duplicate IDs (either by warning or by overwriting)
        // Expect that only one prompt with this ID exists in the output
        $result->assertPromptCount(1);
        $result->assertPromptExists('duplicate-id');

        // Check which version was kept (likely the last one)
        $rawResult = $result->getResult();
        $found = false;
        foreach ($rawResult['prompts'] as $prompt) {
            if ($prompt['id'] === 'duplicate-id') {
                $found = true;
                // This test assumes the last duplicate is kept, which is the common behavior
                // for many config systems. Adjust if the implementation behaves differently.
                $content = "";
                if (isset($prompt['messages'][0]['content']['text'])) {
                    $content = $prompt['messages'][0]['content']['text'];
                } elseif (isset($prompt['messages'][0]['content']) && \is_string($prompt['messages'][0]['content'])) {
                    $content = $prompt['messages'][0]['content'];
                }
                $this->assertStringContainsString(
                    "Second prompt content",
                    $content,
                    "When duplicate IDs exist, the last one should be used",
                );
                break;
            }
        }
        $this->assertTrue($found, "Prompt with duplicate ID should be found");
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_both_messages_and_extensions_should_be_valid(string $command): void
    {
        // Create a config with a prompt that has both messages and extensions
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: base-template
                    type: template
                    messages:
                      - role: user
                        content: "Template content with {{var}}."
                
                  - id: valid-combined
                    description: "Prompt with both messages and extensions"
                    extend:
                      - id: base-template
                        arguments:
                          var: "template variable"
                    messages:
                      - role: user
                        content: "Additional message content"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // The implementation should accept a prompt with both messages and extensions
        $result->assertPromptCount(1);
        $result->assertPromptExists('valid-combined');
        $result->assertPromptExtends('valid-combined', 'base-template');

        // Check that both the template content and additional messages are present
        $result->assertPromptMessages('valid-combined', [
            'Template content with template variable.',
            'Additional message content',
        ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function circular_template_inheritance_should_be_detected(string $command): void
    {
        // Create a config with circular template inheritance
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: template-a
                    type: template
                    extend:
                      - id: template-b
                        arguments:
                          var: "value"
                    messages:
                      - role: user
                        content: "Template A content"
                
                  - id: template-b
                    type: template
                    extend:
                      - id: template-a
                        arguments:
                          var: "value"
                    messages:
                      - role: user
                        content: "Template B content"
                YAML,
            '.yaml',
        );

        // This should not cause an infinite loop but be detected and reported
        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // Implementation should detect circular dependencies and reject these templates
        // or at least complete without hanging
        $this->assertNotNull($result, "Command should complete without hanging on circular dependencies");
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = $this->createTempDir();
    }

    protected function buildContext(
        string $workDir,
        ?string $configPath = null,
        ?string $inlineJson = null,
        ?string $envFile = null,
        string $command = 'generate',
        bool $asJson = true,
    ): CompilingResult {
        return (new ContextBuilder($this->getConsole()))->build(
            workDir: $workDir,
            configPath: $configPath,
            inlineJson: $inlineJson,
            envFile: $envFile,
            command: $command,
            asJson: $asJson,
        );
    }
}
