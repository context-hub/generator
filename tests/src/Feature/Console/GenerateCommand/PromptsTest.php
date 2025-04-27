<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class PromptsTest extends ConsoleTestCase
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
    public function basic_prompts_should_be_compiled(string $command): void
    {
        // Create a basic prompt configuration file
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: test-prompt
                    description: "A simple test prompt"
                    schema:
                      properties:
                        name:
                          description: "User's name"
                      required:
                        - name
                    messages:
                      - role: user
                        content: "Hello {{name}}, this is a test prompt."
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertPromptExists('test-prompt')
            ->assertPrompt('test-prompt', [
                'type' => 'prompt',
                'description' => 'A simple test prompt',
            ])
            ->assertPromptMessages('test-prompt', [
                'Hello {{name}}, this is a test prompt.',
            ])
            ->assertPromptSchema(
                'test-prompt',
                [
                    'name' => ['description' => "User's name"],
                ],
                ['name'],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function multiple_prompts_should_be_compiled(string $command): void
    {
        // Create a configuration with multiple prompts
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: first-prompt
                    description: "First test prompt"
                    schema:
                      properties:
                        name:
                          description: "User's name"
                      required:
                        - name
                    messages:
                      - role: user
                        content: "Hello {{name}}, this is the first prompt."
                
                  - id: second-prompt
                    description: "Second test prompt"
                    schema:
                      properties:
                        query:
                          description: "User's query"
                      required:
                        - query
                    messages:
                      - role: assistant
                        content: "I'll help you with: {{query}}"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertPromptCount(2)
            ->assertPromptExists('first-prompt')
            ->assertPromptExists('second-prompt')
            ->assertPrompt('first-prompt', [
                'type' => 'prompt',
                'description' => 'First test prompt',
            ])
            ->assertPrompt('second-prompt', [
                'type' => 'prompt',
                'description' => 'Second test prompt',
            ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_templates_should_be_compiled(string $command): void
    {
        // Create a configuration with template and prompt that extends it
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: base-template
                    type: template
                    messages:
                      - role: user
                        content: "This is a base template with {{variable}}."
                
                  - id: extended-prompt
                    type: prompt
                    description: "Prompt that extends a template"
                    extend:
                      - id: base-template
                        arguments:
                          variable: "custom value"
                    schema:
                      properties:
                        additionalVar:
                          description: "Additional parameter"
                      required:
                        - additionalVar
                    messages:
                      - role: user
                        content: "Additional message with {{additionalVar}}."
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertPromptExists('extended-prompt')
            ->assertPrompt('extended-prompt', [
                'type' => 'prompt',
                'description' => 'Prompt that extends a template',
            ])
            ->assertPromptExtends('extended-prompt', 'base-template')
            ->assertPromptTemplateArguments('extended-prompt', 'base-template', [
                'variable' => 'custom value',
            ])
            ->assertPromptMessages('extended-prompt', [
                'This is a base template with custom value.',
                'Additional message with {{additionalVar}}.',
            ])
            ->assertPromptSchema(
                'extended-prompt',
                [
                    'additionalVar' => ['description' => 'Additional parameter'],
                ],
                ['additionalVar'],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function complex_prompt_inheritance_should_be_compiled(string $command): void
    {
        // Create a configuration with multiple levels of template inheritance
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: base-template
                    type: template
                    messages:
                      - role: user
                        content: "Base content with {{baseVar}}."
                
                  - id: intermediate-template
                    type: template
                    extend:
                      - id: base-template
                        arguments:
                          baseVar: "{{intermediateVar}}"
                    messages:
                      - role: user
                        content: "Intermediate content with {{intermediateVar}}."
                
                  - id: final-prompt
                    type: prompt
                    description: "Multi-level inherited prompt"
                    extend:
                      - id: intermediate-template
                        arguments:
                          intermediateVar: "final value"
                    messages:
                      - role: user
                        content: "Final content."
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertPromptExists('final-prompt')
            ->assertPromptExtends('final-prompt', 'intermediate-template')
            ->assertPromptTemplateArguments('final-prompt', 'intermediate-template', [
                'intermediateVar' => 'final value',
            ])
            ->assertPromptMessages('final-prompt', [
                'Base content with final value.',
                'Intermediate content with final value.',
                'Final content.',
            ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompt_with_complex_schema_should_be_compiled(string $command): void
    {
        // Create a configuration with a complex schema
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: complex-schema-prompt
                    description: "Prompt with complex schema"
                    schema:
                      properties:
                        title:
                          description: "Document title"
                        sections:
                          description: "Document sections"
                        author:
                          description: "Document author"
                        tags:
                          description: "Document tags"
                      required:
                        - title
                        - author
                    messages:
                      - role: user
                        content: "Create a document titled '{{title}}' by {{author}} with sections on {{sections}}. Tags: {{tags}}"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertPromptExists('complex-schema-prompt')
            ->assertPromptSchema(
                'complex-schema-prompt',
                [
                    'title' => ['description' => 'Document title'],
                    'sections' => ['description' => 'Document sections'],
                    'author' => ['description' => 'Document author'],
                    'tags' => ['description' => 'Document tags'],
                ],
                ['title', 'author'],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function prompts_with_tags_should_be_compiled(string $command): void
    {
        // Create a configuration with prompts having tags
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: coding-prompt
                    description: "Coding assistant prompt"
                    tags: ["coding", "development", "programming"]
                    messages:
                      - role: user
                        content: "Help me with coding."
                
                  - id: writing-prompt
                    description: "Writing assistant prompt"
                    tags: ["writing", "content", "creativity"]
                    messages:
                      - role: user
                        content: "Help me with writing."
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        $result->assertSuccess();

        // Get raw result to examine the tags
        $rawResult = $result->getResult();

        // Find the prompts in the result
        $codingPrompt = null;
        $writingPrompt = null;

        foreach ($rawResult['prompts'] as $prompt) {
            if ($prompt['id'] === 'coding-prompt') {
                $codingPrompt = $prompt;
            } elseif ($prompt['id'] === 'writing-prompt') {
                $writingPrompt = $prompt;
            }
        }

        // Assert the tags are present and correct
        $this->assertNotNull($codingPrompt, 'Coding prompt not found');
        $this->assertNotNull($writingPrompt, 'Writing prompt not found');

        $this->assertEquals(['coding', 'development', 'programming'], $codingPrompt['tags']);
        $this->assertEquals(['writing', 'content', 'creativity'], $writingPrompt['tags']);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function imported_prompts_should_be_compiled(string $command): void
    {
        // Create a base prompts file to be imported
        $basePromptsFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: imported-template
                    type: template
                    description: "Imported template"
                    messages:
                      - role: user
                        content: "This template was imported from another file with {{importedVar}}."
                
                  - id: imported-prompt
                    description: "Imported standalone prompt"
                    schema:
                      properties:
                        query:
                          description: "User's query"
                      required:
                        - query
                    messages:
                      - role: assistant
                        content: "Imported prompt response for: {{query}}"
                YAML,
            '.yaml',
        );

        // Create a main config file that imports the base prompts
        $mainConfigFile = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($basePromptsFile)}
                    type: local
                
                prompts:
                  - id: main-prompt
                    description: "Main file prompt"
                    extend:
                      - id: imported-template
                        arguments:
                          importedVar: "successfully imported value"
                    messages:
                      - role: user
                        content: "Additional content from main file."
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfigFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertImported(path: $this->getRelativePath($basePromptsFile), type: 'local')
            ->assertPromptCount(2) // Only prompts, not templates, should be counted
            ->assertPromptExists('imported-prompt')
            ->assertPromptExists('main-prompt')
            ->assertPromptExtends('main-prompt', 'imported-template')
            ->assertPromptTemplateArguments('main-prompt', 'imported-template', [
                'importedVar' => 'successfully imported value',
            ])
            ->assertPromptMessages('main-prompt', [
                'This template was imported from another file with successfully imported value.',
                'Additional content from main file.',
            ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function imported_prompts_with_filter_should_be_compiled(string $command): void
    {
        // Create a prompts file with multiple prompts to be selectively imported
        $promptsFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: coding-helper
                    description: "Helps with coding tasks"
                    tags: ["coding", "development"]
                    messages:
                      - role: user
                        content: "I need help with coding."
                
                  - id: design-helper
                    description: "Helps with design tasks"
                    tags: ["design", "ui"]
                    messages:
                      - role: user
                        content: "I need help with design."
                
                  - id: advanced-coding
                    description: "Advanced coding assistance"
                    tags: ["coding", "advanced"]
                    messages:
                      - role: user
                        content: "I need advanced coding help."
                YAML,
            '.yaml',
        );

        // Create a main config that imports with a filter
        $mainConfigFile = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($promptsFile)}
                    type: local
                    filter:
                      tags:
                        include: ["coding"]
                        exclude: ["advanced"]
                
                prompts:
                  - id: local-prompt
                    description: "Local prompt"
                    messages:
                      - role: user
                        content: "This is a local prompt."
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfigFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertImported(path: $this->getRelativePath($promptsFile), type: 'local')
            ->assertPromptCount(
                2,
            ) // Local prompt + coding-helper (design-helper and advanced-coding should be filtered out)
            ->assertPromptExists('coding-helper')
            ->assertPromptExists('local-prompt')
            ->assertPrompt('coding-helper', [
                'description' => 'Helps with coding tasks',
            ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function imported_prompts_with_ids_filter_should_be_compiled(string $command): void
    {
        // Create a prompts file with multiple prompts to be selectively imported by ID
        $promptsFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: prompt-one
                    description: "First prompt"
                    messages:
                      - role: user
                        content: "This is the first prompt."
                
                  - id: prompt-two
                    description: "Second prompt"
                    messages:
                      - role: user
                        content: "This is the second prompt."
                
                  - id: prompt-three
                    description: "Third prompt"
                    messages:
                      - role: user
                        content: "This is the third prompt."
                YAML,
            '.yaml',
        );

        // Create a main config that imports specific prompts by ID
        $mainConfigFile = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($promptsFile)}
                    type: local
                    filter:
                      ids: ["prompt-one", "prompt-three"]
                
                prompts:
                  - id: local-prompt
                    description: "Local prompt"
                    messages:
                      - role: user
                        content: "This is a local prompt."
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfigFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertImported(path: $this->getRelativePath($promptsFile), type: 'local')
            ->assertPromptCount(3) // Local prompt + prompt-one + prompt-three
            ->assertPromptExists('prompt-one')
            ->assertPromptExists('prompt-three')
            ->assertPromptExists('local-prompt');
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function invalid_prompt_should_report_error(string $command): void
    {
        // Create a configuration with an invalid prompt (missing required fields)
        $configFile = $this->createTempFile(
            <<<'YAML'
                prompts:
                  - id: invalid-prompt
                    # Missing description
                    # Missing messages
                YAML,
            '.yaml',
        );

        // Execute command which should report an error but not fail the process
        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $configFile,
            command: $command,
        );

        // At minimum, the prompt count should be 0
        $result->assertPromptCount(0);
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

    private function getRelativePath(string $absolutePath): string
    {
        // Convert absolute path to relative path for use in YAML configurations
        // This ensures the test is independent of the absolute paths on the test system
        return \basename($absolutePath);
    }
}
