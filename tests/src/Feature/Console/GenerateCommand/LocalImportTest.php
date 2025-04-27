<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class LocalImportTest extends ConsoleTestCase
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
    public function basic_local_import_should_be_rendered(string $command): void
    {
        // Create a base config file
        $baseConfig = $this->createTempFile(
            <<<'YAML'
                documents:
                  - description: "Base Configuration"
                    outputPath: "base.md"
                    sources:
                      - type: text
                        description: "Base content"
                        content: "This is the base configuration content"
                        tag: "BASE_CONTENT"
                YAML,
            '.yaml',
        );

        // Create a main config that imports the base config
        $mainConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($baseConfig)}
                    type: local
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertImported(path: $this->getRelativePath($baseConfig), type: 'local')
            ->assertContext(
                document: 'base.md',
                contains: [
                    '# Base Configuration',
                    'This is the base configuration content',
                    '<BASE_CONTENT>',
                    '</BASE_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration content',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function local_import_with_path_prefix_should_be_applied(string $command): void
    {
        // Create a base config file
        $baseConfig = $this->createTempFile(
            <<<'YAML'
                documents:
                  - description: "Prefixed Document"
                    outputPath: "original.md"
                    sources:
                      - type: text
                        description: "Prefixed content"
                        content: "This content should have its path prefixed"
                        tag: "PREFIXED_CONTENT"
                YAML,
            '.yaml',
        );

        // Create a main config that imports the base config with a path prefix
        $mainConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($baseConfig)}
                    type: local
                    pathPrefix: "prefixed" 
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertImported(path: $this->getRelativePath($baseConfig), type: 'local')
            ->assertContext(
                document: 'prefixed/original.md',
                contains: [
                    '# Prefixed Document',
                    'This content should have its path prefixed',
                    '<PREFIXED_CONTENT>',
                    '</PREFIXED_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration content',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function local_import_with_selective_documents_should_work(string $command): void
    {
        // Create a base config file with multiple documents
        $baseConfig = $this->createTempFile(
            <<<'YAML'
                documents:
                  - description: "First Document"
                    outputPath: "first.md"
                    sources:
                      - type: text
                        description: "First content"
                        content: "This is the first document content"
                        tag: "FIRST_CONTENT"
                  - description: "Second Document"
                    outputPath: "second.md"
                    sources:
                      - type: text
                        description: "Second content"
                        content: "This is the second document content"
                        tag: "SECOND_CONTENT"
                  - description: "Third Document"
                    outputPath: "third.md"
                    sources:
                      - type: text
                        description: "Third content"
                        content: "This is the third document content"
                        tag: "THIRD_CONTENT"
                YAML,
            '.yaml',
        );

        // Create a main config that imports only selected documents
        $mainConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($baseConfig)}
                    type: local
                    docs:
                      - "first.md"
                      - "third.md"
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertImported(path: $this->getRelativePath($baseConfig), type: 'local')
            ->assertContext(
                document: 'first.md',
                contains: [
                    '# First Document',
                    'This is the first document content',
                    '<FIRST_CONTENT>',
                    '</FIRST_CONTENT>',
                ],
            )
            ->assertMissedContext(document: 'second.md')
            ->assertContext(
                document: 'third.md',
                contains: [
                    '# Third Document',
                    'This is the third document content',
                    '<THIRD_CONTENT>',
                    '</THIRD_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration content',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );

        // Verify second.md was not generated
        $this->assertFileDoesNotExist("{$this->outputDir}/second.md");
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function nested_local_imports_should_be_processed(string $command): void
    {
        // Create a deeply nested config
        $nestedConfig = $this->createTempFile(
            <<<'YAML'
                documents:
                  - description: "Nested Document"
                    outputPath: "nested.md"
                    sources:
                      - type: text
                        description: "Nested content"
                        content: "This is a deeply nested config document"
                        tag: "NESTED_CONTENT"
                YAML,
            '.yaml',
        );

        // Create a middle-level config that imports the nested config
        $middleConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($nestedConfig)}
                    type: local
                
                documents:
                  - description: "Middle Document"
                    outputPath: "middle.md"
                    sources:
                      - type: text
                        description: "Middle content"
                        content: "This is the middle-level configuration"
                        tag: "MIDDLE_CONTENT"
                YAML,
            '.yaml',
        );

        // Create a main config that imports the middle config
        $mainConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($middleConfig)}
                    type: local
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the top-level configuration"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $mainConfig,
            command: $command,
        );

        $result->assertDocumentsCompiled();

        // All three documents should be rendered
        $result->assertContext(
            document: 'nested.md',
            contains: [
                '# Nested Document',
                'This is a deeply nested config document',
                '<NESTED_CONTENT>',
                '</NESTED_CONTENT>',
            ],
        );

        $result->assertContext(
            document: 'middle.md',
            contains: [
                '# Middle Document',
                'This is the middle-level configuration',
                '<MIDDLE_CONTENT>',
                '</MIDDLE_CONTENT>',
            ],
        );

        $result->assertContext(
            document: 'main.md',
            contains: [
                '# Main Document',
                'This is the top-level configuration',
                '<MAIN_CONTENT>',
                '</MAIN_CONTENT>',
            ],
        );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function circular_imports_should_be_detected(string $command): void
    {
        // Create first config that will import the second
        $firstConfig = $this->createTempFile(
            <<<'YAML'
                # This will be filled in later after we create the second config
                YAML,
            '.yaml',
        );

        // Create second config that imports the first (creating a circular dependency)
        $secondConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($firstConfig)}
                    type: local
                
                documents:
                  - description: "Second Document"
                    outputPath: "second.md"
                    sources:
                      - type: text
                        description: "Second content"
                        content: "This is the second configuration"
                        tag: "SECOND_CONTENT"
                YAML,
            '.yaml',
        );

        // Now update the first config to import the second
        \file_put_contents(
            $firstConfig,
            <<<YAML
                import:
                  - path: {$this->getRelativePath($secondConfig)}
                    type: local
                
                documents:
                  - description: "First Document"
                    outputPath: "first.md"
                    sources:
                      - type: text
                        description: "First content"
                        content: "This is the first configuration"
                        tag: "FIRST_CONTENT"
                YAML,
        );

        // The command should either fail gracefully or skip the circular import
        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $firstConfig,
            command: $command,
        );

        // Processing should continue and not enter an infinite loop
        $result->assertDocumentsCompiled();
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function wildcard_imports_should_be_processed(string $command): void
    {
        // Create a directory for wildcard imports
        $wildcardDir = $this->createTempDir();

        // Create multiple config files in the wildcard directory
        $firstWildcardConfig = $this->createTempFile(
            <<<'YAML'
                documents:
                  - description: "First Wildcard Document"
                    outputPath: "wildcard1.md"
                    sources:
                      - type: text
                        description: "Wildcard 1 content"
                        content: "This is the first wildcard configuration"
                        tag: "WILDCARD1_CONTENT"
                YAML,
            '.yaml',
        );

        $secondWildcardConfig = $this->createTempFile(
            <<<'YAML'
                documents:
                  - description: "Second Wildcard Document"
                    outputPath: "wildcard2.md"
                    sources:
                      - type: text
                        description: "Wildcard 2 content"
                        content: "This is the second wildcard configuration"
                        tag: "WILDCARD2_CONTENT"
                YAML,
            '.yaml',
        );

        // Create a main config that imports using wildcard pattern
        $mainConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($wildcardDir)}/*.yaml
                    type: local
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'wildcard1.md',
                contains: [
                    '# First Wildcard Document',
                    'This is the first wildcard configuration',
                    '<WILDCARD1_CONTENT>',
                    '</WILDCARD1_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'wildcard2.md',
                contains: [
                    '# Second Wildcard Document',
                    'This is the second wildcard configuration',
                    '<WILDCARD2_CONTENT>',
                    '</WILDCARD2_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function variables_from_imported_configs_should_be_merged(string $command): void
    {
        // Create a base config with variables
        $baseConfig = $this->createTempFile(
            <<<'YAML'
                variables:
                  BASE_VAR: "base-variable-value"
                  SHARED_VAR: "base-shared-value"
                
                documents:
                  - description: "Base Variable Document"
                    outputPath: "base-vars.md"
                    sources:
                      - type: text
                        description: "Base variable content"
                        content: "Base variable: {{BASE_VAR}}"
                        tag: "BASE_VAR_CONTENT"
                YAML,
            '.yaml',
        );

        // Create a main config that imports the base config and overrides a variable
        $mainConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($baseConfig)}
                    type: local
                
                variables:
                  MAIN_VAR: "main-variable-value"
                  SHARED_VAR: "main-shared-value" # This should override the imported one
                
                documents:
                  - description: "Main Variable Document"
                    outputPath: "main-vars.md"
                    sources:
                      - type: text
                        description: "Main variable content"
                        content: "Main variable: {{MAIN_VAR}}, Base variable: {{BASE_VAR}}, Shared variable: {{SHARED_VAR}}"
                        tag: "MAIN_VAR_CONTENT"
                YAML,
            '.yaml',
        );

        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $mainConfig,
            command: $command,
        );

        $result->assertDocumentsCompiled();

        // Base document should use base variables
        $result->assertContext(
            document: 'base-vars.md',
            contains: [
                '# Base Variable Document',
                'Base variable: base-variable-value',
                '<BASE_VAR_CONTENT>',
                '</BASE_VAR_CONTENT>',
            ],
        );

        // Main document should have access to both sets of variables,
        // with main config variables taking precedence for shared names
        $result->assertContext(
            document: 'main-vars.md',
            contains: [
                '# Main Variable Document',
                'Main variable: main-variable-value',
                'Base variable: base-variable-value',
                'Shared variable: main-shared-value', // Should use the main config value
                '<MAIN_VAR_CONTENT>',
                '</MAIN_VAR_CONTENT>',
            ],
        );
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
